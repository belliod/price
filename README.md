# 美团代理商城市到手价双向计算器（多店铺版）

纯静态网页计算器：支持多个店铺，每家店一套独立扣费参数与备注名称，价格算法全店一致；支持「原价 → 到手价」正向与「到手价 → 原价」反向双向计算，并附原价↔到手价曲线与反向定价速查表。

## 文件说明

| 文件 | 说明 |
| --- | --- |
| index.html | 网页本体（本地双击也可用；部署后自动读写本目录 stores.json） |
| api.php | 数据读写接口：把店铺数据保存到本目录下的 stores.json（群晖 PHP 环境使用） |
| functions/api.js | Cloudflare Pages Function：把店铺数据保存到 Cloudflare KV（Pages 部署时使用，无需 PHP） |
| stores.json | 店铺数据文件（与网页同目录，可直接备份/迁移） |
| README.md | 本说明 |

## 群晖（Synology）部署

1. 把本目录所有文件放入 Web Station 的网站目录（例如 `/web/price`）。
2. 在「套件中心」确认已安装 **Web Station** 与 **PHP**（默认自带 PHP 8.x，无需额外配置）。
3. 浏览器打开 `http://NAS地址/price/index.html` 即可使用。
4. 写入权限：PHP 进程需要能写本目录。若页面提示「服务器文件不可写」，请在 File Station 中右键该目录 → 属性 → 权限，给 `http` 用户（或 Everyone）赋予写入权限；或 SSH 执行 `chmod 777 price/stores.json`。

## 数据存储

- 数据保存在**网页同目录的 stores.json**（服务器模式）。
- 若服务器不可写（如直接双击打开、纯静态托管），页面自动降级为浏览器 localStorage 保存并提示。
- 备份/迁移：直接复制 `stores.json` 一个文件即可。

## Cloudflare Pages 部署（可选，替代群晖）

本工具同时兼容 Cloudflare Pages：静态页面由 Pages 托管，数据读写由 Pages Functions（functions/api.js）完成，存入 Cloudflare KV（云端持久化，无需 PHP）。页面启动时自动探测数据接口：优先 `/api`（Cloudflare），其次 `api.php`（群晖），两者都没有时降级为浏览器本地保存。

1. 在 Cloudflare 控制台 → Workers & Pages → **KV**，创建一个 KV 命名空间（例如 `price-stores`）。
2. **Pages → Create project → Connect to Git**，选择 `belliod/price` 仓库。
3. 框架预设选 **None**；构建命令留空；输出目录填 `/`（仓库根目录）。
4. 项目 **Settings → Functions → KV namespace bindings**：变量名填 `STORES_KV`，绑定第 1 步创建的命名空间。
5. 保存后自动部署。此后**每次 push 到 GitHub 都会自动重新部署**（GitHub → Cloudflare 自动同步，保持更新）。

> 注意：Cloudflare 不运行 PHP，`api.php` 在 Pages 上无效；页面会自动改用 `/api` 读写 KV。

## 使用

- 「添加店铺」新增一家店；点击店铺名称可直接修改备注；点名称旁的 × 删除该店。
- 「搜索店铺名称或ID」输入框：输入关键字实时过滤店铺标签，按名称或 ID 均可检索（店铺 ID 显示在每个店铺标签下方）。
- 每家店独立维护扣费参数：打包费、神券门槛/金额、商家补贴、平台技术服务费率、合作商服务费率（含保底）、配送起步价/价格收费/配送折扣/封顶比例等。
- 反向模式：输入目标到手价，自动给出「神券前不触发」与「神券后触发」两种定价路径并解释。

## 算法说明

核心为 `calc()`（正向）与 `solveReverse()`（反向扫描双分支）两个纯函数，与单店铺原版逻辑完全一致，全店共用。

> 规则来源：美团代理商城市服务费截图。参数均为参考值，请按店铺实际情况修改。