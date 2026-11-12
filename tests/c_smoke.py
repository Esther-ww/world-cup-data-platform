"""成员 C 模块端到端自检。

用法：python tests/c_smoke.py <php.exe>
覆盖：公共页面、空阵容、搜索筛选、分页、权限、CSRF、CRUD、外键保护、
      名单唯一性、阵容人数校验、导入 dry-run 回滚。

测试使用仓库内的临时 SQLite 库和临时会话目录，不修改 var/demo.sqlite。
"""
import csv
import http.cookiejar
import os
import re
import socket
import sqlite3
import subprocess
import sys
import tempfile
import time
import urllib.error as urlerr
import urllib.parse as uparse
import urllib.request as urlreq
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PHP = sys.argv[1] if len(sys.argv) > 1 else "php"


def client():
    return urlreq.build_opener(
        urlreq.ProxyHandler({}),
        urlreq.HTTPCookieProcessor(http.cookiejar.CookieJar()),
    )


class Web:
    def __init__(self, base):
        self.base = base

    def req(self, c, page="", data=None, expect=200):
        try:
            r = c.open(self.base + page, None if data is None else uparse.urlencode(data, doseq=True).encode(), timeout=10)
        except urlerr.HTTPError as e:
            r = e
        with r:
            assert r.status == expect, (page, data, r.status, expect)
            return r.read().decode()

    def csrf(self, c, page="?p=auth"):
        html = self.req(c, page)
        m = re.search(r'name="csrf" value="([^"]+)"', html)
        assert m, "页面缺少 CSRF 令牌"
        return m.group(1)

    def post(self, c, data, expect=200):
        return self.req(c, "", data, expect)


def make_db(db: Path):
    cn = sqlite3.connect(db)
    cn.executescript((ROOT / "data/demo/schema.sql").read_text(encoding="utf8"))
    cn.commit()
    cn.close()


def import_cli(db: Path, kind: str, dry=False):
    env = {**os.environ, "C_DSN": "sqlite:" + str(db)}
    args = [PHP, "bin/import.php", kind] + (["--dry-run"] if dry else [])
    return subprocess.run(args, cwd=ROOT, env=env, capture_output=True, text=True, encoding="utf8")


def main():
    with tempfile.TemporaryDirectory(dir=ROOT, ignore_cleanup_errors=True) as tmp:
        tmp = Path(tmp)
        db = tmp / "c_check.sqlite"
        make_db(db)
        env = {**os.environ, "C_DSN": "sqlite:" + str(db), "C_ADMIN_PASSWORD": "local-test-only-123"}

        # 导入链路：先球队，再占位比赛，再名单与阵容。
        assert import_cli(db, "teams").returncode == 0
        cn = sqlite3.connect(db)
        cn.executescript((ROOT / "data/demo/stub.sql").read_text(encoding="utf8"))
        cn.commit()
        cn.close()
        for kind in ("players", "squads", "lineups", "lineup_players"):
            r = import_cli(db, kind)
            assert r.returncode == 0, (kind, r.stderr)
        subprocess.run([PHP, "bin/setup.php", "admin"], cwd=ROOT, env=env, check=True,
                       stdout=subprocess.DEVNULL)

        # 普通用户（C 模块不提供注册，直接写入测试账号）。
        pw_hash = subprocess.check_output(
            [PHP, "-r", "echo password_hash('userpass123', PASSWORD_DEFAULT);"], text=True
        ).strip()
        cn = sqlite3.connect(db)
        cn.execute("INSERT INTO users(username, password_hash, role) VALUES(?,?,'user')", ("fan", pw_hash))
        cn.commit()
        cn.close()

        with socket.socket() as s:
            s.bind(("127.0.0.1", 0))
            port = s.getsockname()[1]
        base = f"http://127.0.0.1:{port}/"
        env["C_DSN"] = "sqlite:" + str(db)
        server = subprocess.Popen(
            [PHP, "-d", "session.save_path=" + str(tmp), "-S", f"127.0.0.1:{port}", "-t", "public"],
            cwd=ROOT, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
        )
        web = Web(base)
        try:
            guest, fan, admin = client(), client(), client()
            for _ in range(50):
                try:
                    web.req(guest)
                    break
                except OSError:
                    time.sleep(0.1)

            # 公共页面与数据
            teams = web.req(guest, "?p=teams")
            assert "巴西" in teams and "23 名球员" in teams
            team = web.req(guest, "?p=team&id=1")
            assert "巴西" in team and "Neymar" in team and "4-3-3" not in team
            found = web.req(guest, "?p=players&q=Neymar")
            assert "Neymar" in found
            filtered = web.req(guest, "?p=players&team=1&position=FW")
            assert "Neymar" in filtered and "球员" in filtered
            web.req(guest, "?p=players&page=2")
            player = web.req(guest, "?p=player&id=21")
            assert "Neymar" in player and "1992-02-05" in player and "首发" in player
            lineup = web.req(guest, "?p=lineup&match=1")
            assert "4-3-3" in lineup and "pitch-player" in lineup
            empty = web.req(guest, "?p=lineup&match=11")
            assert "阵容未公布" in empty
            web.req(guest, "?p=player&id=99999", expect=404)
            web.req(guest, "?p=nope", expect=404)
            web.req(guest, "?p=admin", expect=403)

            # 普通用户不能进入后台，也不能写
            web.post(fan, {"csrf": web.csrf(fan, "?p=auth"), "action": "login",
                           "username": "fan", "password": "userpass123"})
            web.req(fan, "?p=admin", expect=403)

            # 管理员登录与 CSRF
            web.post(admin, {"csrf": "wrong-token", "action": "login",
                             "username": "admin", "password": "local-test-only-123"}, expect=403)
            login_page = web.post(admin, {"csrf": web.csrf(admin, "?p=auth"), "action": "login",
                                          "username": "admin", "password": "local-test-only-123"})
            assert "已登录" in login_page, "登录后的提示未显示"
            admin_page = web.req(admin, "?p=admin&tab=team")
            assert "管理后台" in admin_page and "球队列表" in admin_page
            csrf = web.csrf(admin, "?p=admin&tab=team")

            # 球队 CRUD 与外键保护
            created = web.post(admin, {"csrf": csrf, "action": "teamSave", "id": 0,
                                       "name": "测试队", "name_en": "Test", "country_code": "TST"})
            assert "球队已创建" in created
            team_page = web.req(admin, "?p=admin&tab=team")
            assert "测试队" in team_page
            web.post(admin, {"csrf": csrf, "action": "teamSave", "id": 0,
                             "name": "重复代码", "name_en": "Dup", "country_code": "TST"}, expect=422)
            web.post(admin, {"csrf": csrf, "action": "teamDelete", "id": 1}, expect=422)
            cn = sqlite3.connect(db)
            test_team = cn.execute("SELECT id FROM teams WHERE country_code='TST'").fetchone()[0]
            cn.close()
            web.post(admin, {"csrf": csrf, "action": "teamDelete", "id": test_team})
            assert "测试队" not in web.req(admin, "?p=admin&tab=team")

            # 球员 CRUD 与外键保护
            web.post(admin, {"csrf": csrf, "action": "playerSave", "id": 0, "name": "测试球员甲",
                             "name_en": "Test A", "birth_date": "", "nationality": "测试",
                             "position": "DF"})
            web.post(admin, {"csrf": csrf, "action": "playerSave", "id": 0, "name": "测试球员乙",
                             "name_en": "Test B", "birth_date": "2000-01-01", "nationality": "测试",
                             "position": "MF"})
            web.post(admin, {"csrf": csrf, "action": "playerDelete", "id": 21}, expect=422)
            cn = sqlite3.connect(db)
            pid_a, pid_b = [r[0] for r in cn.execute(
                "SELECT id FROM players WHERE name IN ('测试球员甲','测试球员乙') ORDER BY id")]
            cn.close()

            # 名单：重复号码拒绝、添加/移出
            web.post(admin, {"csrf": csrf, "action": "squadAdd", "competition_id": 1, "team_id": 1,
                             "player_id": pid_a, "shirt_number": 99})
            web.post(admin, {"csrf": csrf, "action": "squadAdd", "competition_id": 1, "team_id": 1,
                             "player_id": pid_b, "shirt_number": 99}, expect=422)
            assert "测试球员甲" in web.req(admin, "?p=admin&tab=squad&team=1")
            web.post(admin, {"csrf": csrf, "action": "squadDelete", "competition_id": 1,
                             "team_id": 1, "player_id": pid_a})
            web.post(admin, {"csrf": csrf, "action": "playerDelete", "id": pid_a})
            web.post(admin, {"csrf": csrf, "action": "playerDelete", "id": pid_b})

            # 阵容：人数/门将校验、保存、撤下
            cn = sqlite3.connect(db)
            squad = list(cn.execute(
                "SELECT s.player_id, p.position FROM squads s JOIN players p ON p.id = s.player_id "
                "WHERE s.competition_id=1 AND s.team_id=6 "
                "ORDER BY CASE p.position WHEN 'GK' THEN 1 WHEN 'DF' THEN 2 WHEN 'MF' THEN 3 ELSE 4 END"))
            cn.close()
            gk = [p for p, pos in squad if pos == "GK"][0]
            df = [p for p, pos in squad if pos == "DF"][:4]
            mf = [p for p, pos in squad if pos == "MF"][:4]
            fw = [p for p, pos in squad if pos == "FW"][:2]
            starters = [gk] + df + mf + fw
            fields = {"csrf": csrf, "action": "lineupSave", "match_id": 11, "team_id": 6,
                      "formation": "4-4-2"}
            for pid in starters:
                fields[f"role[{pid}]"] = "starter"
            web.post(admin, fields)
            saved = web.req(guest, "?p=lineup&match=11")
            assert "4-4-2" in saved and saved.count("阵容未公布") == 1

            bad = {"csrf": csrf, "action": "lineupSave", "match_id": 11, "team_id": 6,
                   "formation": "4-4-2"}
            for pid in starters[:-1]:
                bad[f"role[{pid}]"] = "starter"
            web.post(admin, bad, expect=422)
            assert "4-4-2" in web.req(guest, "?p=lineup&match=11")

            web.post(admin, {"csrf": csrf, "action": "lineupDelete", "match_id": 11, "team_id": 6})
            assert web.req(guest, "?p=lineup&match=11").count("阵容未公布") == 2

            # 导入 dry-run：校验成功但回滚，随后真正导入
            dry_db = tmp / "dry.sqlite"
            make_db(dry_db)
            r = import_cli(dry_db, "teams", dry=True)
            assert r.returncode == 0, r.stderr
            cn = sqlite3.connect(dry_db)
            assert cn.execute("SELECT COUNT(*) FROM teams").fetchone()[0] == 0
            cn.close()
            assert import_cli(dry_db, "teams").returncode == 0
            cn = sqlite3.connect(dry_db)
            assert cn.execute("SELECT COUNT(*) FROM teams").fetchone()[0] == 8
            cn.close()

            # 退出后不能继续访问后台
            logout_csrf = web.csrf(admin, "?p=teams")
            web.post(admin, {"csrf": logout_csrf, "action": "logout"})
            web.req(admin, "?p=admin", expect=403)

            print("PASS: 公共页面、空阵容、搜索筛选、分页、权限、CSRF、球队/球员 CRUD、外键保护、"
                  "名单号码唯一、阵容 11 人校验、撤下阵容、导入 dry-run 回滚、退出")
        finally:
            if server:
                server.terminate()
                server.wait()


if __name__ == "__main__":
    main()
