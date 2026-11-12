<?php
/**
 * 成员 C | 公共入口：仅 public 目录对外提供 Web 服务。
 *
 * 部署时 Web 根目录必须指向 public/，不要暴露 src/、data/ 或 var/。
 * 路由与权限见 src/PlayerController.php。
 */
require dirname(__DIR__) . '/src/PlayerController.php';
