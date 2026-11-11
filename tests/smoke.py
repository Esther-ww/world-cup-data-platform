"""A 模块端到端自检：启动独立测试库，不修改用户已有数据。用法 python tests/smoke.py <php.exe>"""
import os, sys, sqlite3, tempfile, subprocess, socket, time, re, urllib.request as u, urllib.error as er, urllib.parse as p, http.cookiejar
from pathlib import Path
root=Path(__file__).resolve().parents[1]
php=sys.argv[1] if len(sys.argv)>1 else 'php'
def client():return u.build_opener(u.ProxyHandler({}),u.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def req(c,page='',data=None,expect=200):
    try:r=c.open(base+page,None if data is None else p.urlencode(data).encode(),timeout=5)
    except er.HTTPError as e:r=e
    with r:
        assert r.status==expect,(page,r.status,expect)
        return r.read().decode()
def post(c,a,expect=200,**fields):
    csrf=re.search('name="csrf" value="([^"]+)"',req(c,'?p=auth'))[1]
    return req(c,'',dict(csrf=csrf,action=a,**fields),expect)
with tempfile.TemporaryDirectory(dir=root) as d:
    db=Path(d)/'check.sqlite'
    cn=sqlite3.connect(db)
    for n in ('catalog','schema'):cn.executescript((root/f'data/demo/{n}.sql').read_text(encoding='utf8'))
    cn.close()
    env={**os.environ,'A_DSN':'sqlite:'+str(db.relative_to(root)),'A_ADMIN_PASSWORD':'local-test-only-123'}
    subprocess.run([php,'bin/setup.php','admin'],cwd=root,env=env,check=True,stdout=subprocess.DEVNULL)
    with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
    base=f'http://127.0.0.1:{port}/'
    env['A_DSN']='sqlite:'+str(db)
    server=subprocess.Popen([php,'-d','session.save_path='+d,'-S',f'127.0.0.1:{port}','-t','public'],env=env,cwd=root,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    try:
        a,b,admin,guest=[client() for _ in range(4)]
        for i in range(40):
            try:req(guest);break
            except OSError:time.sleep(.1)
        req(guest,'?p=admin',expect=403)
        for c,n in [(a,'fan_one'),(b,'fan_two')]:post(c,'register',username=n,password='local-test123')
        req(a,'',dict(csrf='wrong',action='message',body='example'),403)
        post(a,'approve',403,id=1)
        for t in ('match','match','team'):post(a,'favorite',type=t,id=1,save=1)
        assert req(a,'?p=profile').count('取消收藏')==2 and '取消收藏' not in req(b,'?p=profile')
        post(a,'favorite',type='team',id=1,save=0)
        post(a,'favorite',422,type='team',id=999,save=1)
        post(a,'message',body='课程测试留言')
        assert '课程测试留言' not in req(guest)
        post(admin,'login',username='admin',password=env['A_ADMIN_PASSWORD'])
        post(admin,'approve',id=1);assert '课程测试留言' in req(guest)
        post(admin,'delete',id=1);assert '课程测试留言' not in req(guest)
        post(admin,'updateTeam',name='测试队名',contact='',intro='测试简介');assert '测试队名' in req(guest)
        post(admin,'updateMember',id=1,intro='测试成员');assert '测试成员' in req(guest)
        assert '需求' in req(guest,'?p=file&id=1')
        post(admin,'unpublish',id=1);req(guest,'?p=file&id=1',expect=404)
        post(admin,'publish',file_key='requirements',member_id='',title='重新发布');assert '重新发布' in req(guest,'?p=downloads')
        post(a,'logout');req(a,'?p=profile',expect=403)
        print('PASS: auth, CSRF, roles, favorite isolation/deduplication, moderation, team edit, downloads, logout')
    finally:
        if server:server.terminate();server.wait()


