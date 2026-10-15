# 企业内部邮件协同办公系统 (Enterprise Internal Mail System)

一套专为企业局域网/内网设计的内部协同邮件系统，服务规模约为 100–500 名员工。不依赖公网 SMTP、第三方邮箱服务或外部邮件服务器，提供端到端数据私密性、组织架构通讯录、已读回执追踪、安全审批流与国产数据库（人大金仓 KingbaseES）平滑迁移支持。

---

## 🌟 核心特性

1. **纯内部邮件通信**：
   - 支持主送（TO）、抄送（CC）、密送（BCC）多收件人。
   - 密送（BCC）隔离保护：接收方绝不泄露密送人信息。
   - 收件箱、已发送、草稿箱、星标与软删除恢复。
   - 精确的“已读回执”状态与已读时间记录。

2. **直属部门经理审批流 (重要合规机制)**：
   - 普通员工外发/提交的邮件默认进入待审批（`pending`）状态，防止敏感信息违规外发。
   - **严格按照组织架构隔离**：发件人所在部门的**直属部门经理（如研发经理、运营经理）**才有权审批本部门员工邮件；审批通过后系统自动投递至对方收件箱；支持驳回并附带修改意见。

3. **现代富文本编辑器与高级排版**：
   - 集成 **Quill 富文本编辑器**，支持多级标题、字号颜色、高亮背景、列表、对齐排版及超链接。
   - 邮件详情原生安全解析与排版展示。

4. **精致的附件上传与私有存储**：
   - 现代化拖拽/点击上传区，支持文件卡片网格化展示（文件名防溢出、格式化大小、快速删除）。
   - 服务端哈希去重、防路径穿越校验、可执行脚本白名单拦截与授权安全下载。

5. **草稿箱二次编辑与一键投递**：
   - 草稿箱邮件支持随时重新打开，自动回填主题、富文本正文、收件人列表与已有附件。
   - 支持在原草稿基础上保存更新或直接发送。

6. **企业组织架构通讯录**：
   - 左侧部门分类导航树（总经办、研发中心、运营部等）联动员工列表。
   - 支持按姓名、用户名、职位、邮箱及手机号即时多关键字过滤。
   - 提供快捷一键“发邮件”入口，直接填入收件人。

7. **双数据库适配与国产化支持 (人大金仓 KingbaseES)**：
   - **默认数据库**：MySQL 8.0 / 5.7+。
   - **国产数据库支持**：支持人大金仓 KingbaseES (PostgreSQL 模式 / 原生 KES 驱动)。
   - 内置统一 Schema 管理层与 SQL 方言转换工具，支持在控制台进行**目标库连通性测试、Schema 预览、表结构转换、数据迁移与一致性校验**。

---

## 🛠️ 技术选型

- **后端**：PHP 8.0+、Webman v1 (Workerman 高性能常驻服务框架)、Composer
- **前端**：Vue 3、Element Plus、Quill 富文本编辑器、Axios
- **认证**：基于 JWT (JSON Web Token) 的无状态认证与 RBAC 权限体系
- **默认数据库**：MySQL 8.0 / 5.7+
- **目标国产库**：人大金仓 KingbaseES V8 / V9

---

## 🚀 快速启动指南

### 1. 环境准备
确保本机或服务器安装了以下环境：
- PHP >= 8.0 (启用 `pdo_mysql`, `pdo_pgsql`, `fileinfo`, `mbstring`, `openssl`, `json`)
- Composer
- MySQL 数据库

### 2. 安装依赖
```bash
composer install
```

### 3. 配置数据库
在 [`config/database.php`](file:///g:/pro/zfc/emailstsrem/config/database.php) 中修改数据库连接信息（或通过环境变量指定）：
```php
'mysql' => [
    'host'        => '127.0.0.1',
    'port'        => 3306,
    'database'    => 'email_system',
    'username'    => 'root',
    'password'    => '123456',
    'charset'     => 'utf8mb4',
],
```

### 4. 初始化数据表与初始数据
运行数据表迁移脚本，自动创建 10 张核心业务表并导入组织部门与初始测试账号：
```bash
php -d opcache.enable_cli=0 bin/migrate.php
```

### 5. 启动服务
- **Windows 本地运行**：
  ```bash
  # 方式 1：双击运行 windows.bat
  # 方式 2：CLI 执行
  php -d opcache.enable_cli=0 windows.php
  # 或直接运行独立 worker
  php -d opcache.enable_cli=0 runtime/windows/start_webman.php
  ```
- **Linux 生产环境守护运行**：
  ```bash
  php start.php start -d
  ```

---

## 🌐 访问地址与预设账号

系统默认运行端口为：**`8781`**

- **系统访问入口**：[http://127.0.0.1:8781](http://127.0.0.1:8781)
- **初始测试账号列表（默认密码统一为：`123456`）**：

| 账号 | 姓名 | 角色与职位 | 所属部门 | 业务与审批权限说明 |
| :--- | :--- | :--- | :--- | :--- |
| `admin` | 系统管理员 | 系统管理员 (`admin`) | 总经办 | 全系统运维、部门通讯录管理、系统配置 |
| `dbadmin` | 数据库管理员 | 迁移管理员 (`db_admin`) | 总经办 | 专享人大金仓 KingbaseES 迁移与切换运维中心 |
| `lisi` | 李四 | 研发经理 (`manager`) | 研发中心 | **直属经理**：负责审批研发部下属员工（如张三）发出的邮件 |
| `zhangsan`| 张三 | 前端工程师 (`user`) | 研发中心 | 普通员工：写邮件发送后自动提交给研发经理 `lisi` 审批 |
| `wangwu` | 王五 | 运营主管/经理 (`manager`) | 运营部 | **直属经理**：负责审批运营部下属员工发出的邮件 |

---

## 📖 核心业务操作示例

### 1. 邮件外发审批演示
1. 登录研发员工账号 `zhangsan`（密码 `123456`）；
2. 点击**“写邮件”**，使用富文本排版正文，添加附件并点击“立即发送”；
3. 系统提示：`邮件已提交审核，待直属经理审批通过后将自动外发`；张三在“已发送邮件”中可见黄色 `待审批` 状态标签；
4. 切换登录研发经理 `lisi`（密码 `123456`），左侧菜单点击 **“部门邮件审批”**；
5. 查看张三提交的邮件正文与附件，点击 **“批准外发”**；
6. 邮件正式进入收件人的收件箱，实现企业安全审核闭环。

### 2. 草稿箱编辑与发送
1. 写邮件时点击**“保存草稿”**，邮件存入“草稿箱”；
2. 在草稿箱列表点击**“编辑/发送”**，表单完整回填原正文、收件人和附件；
3. 编辑调整后可继续保存草稿，或点击“立即发送”提交审批投递。

### 3. 国产人大金仓 KingbaseES 迁移中心
1. 登录 `dbadmin` 账号，进入左侧 **“数据库管理与迁移”**；
2. **连接测试**：输入目标服务器 KingbaseES 地址、端口（默认 54321）及账密，测试网络与驱动协议连通性；
3. **Schema 结构预览**：系统内置统一方言生成器，自动生成对应 KingbaseES 的 `CREATE TABLE`、`SERIAL` 自增序列与索引 DDL；
4. **数据迁移与校验**：一键生成迁移任务，分批同步数据并在目标端校准序列与记录一致性。

---

## 📁 项目目录结构

```text
emailstsrem/
├── app/
│   ├── controller/api/
│   │   ├── AuthController.php          # 登录认证与用户信息接口
│   │   ├── MailController.php          # 邮件收发、草稿、详情、审核流接口
│   │   ├── AttachmentController.php    # 附件安全上传与授权下载接口
│   │   ├── ContactController.php       # 企业通讯录与部门联动接口
│   │   └── DatabaseAdminController.php # 数据库管理与 KingbaseES 迁移接口
│   ├── service/
│   │   ├── AuthService.php             # JWT 签名与鉴权服务
│   │   ├── MailService.php             # 核心邮件收发、BCC隔离与直属经理审批服务
│   │   ├── AttachmentService.php       # 附件哈希去重与防穿越存储服务
│   │   ├── MigrationService.php        # 核心表结构迁移与初始种子数据服务
│   │   └── DatabaseMigrationService.php# MySQL → KingbaseES 结构与数据迁移服务
│   ├── database/
│   │   ├── DatabaseManager.php         # 数据库连接工厂与运行时配置切换
│   │   ├── DatabaseAdapterInterface.php# 统一数据库方言接口定义
│   │   ├── MySqlAdapter.php            # MySQL 8.0/5.7 方言适配器
│   │   ├── KingbaseAdapter.php         # 人大金仓 KingbaseES 方言适配器
│   │   ├── SchemaDefinition.php        # 10张业务表统一 Schema 描述定义
│   │   └── SchemaManager.php           # 版本化迁移管理
│   └── middleware/
│       └── AuthMiddleware.php          # 全局 JWT 路由保护中间件
├── bin/
│   └── migrate.php                     # CLI 数据库安装初始化脚本
├── config/
│   ├── database.php                    # 双数据库连接配置
│   ├── process.php                     # 进程配置与 8781 监听端口
│   ├── route.php                       # RESTful API 路由与 SPA 回退
│   └── middleware.php                  # 中间件配置
├── docs/                               # 需求与工程规范交付文档
│   ├── 01-project-audit.md             # 项目环境审计报告
│   ├── 02-database-compatibility.md    # 双数据库兼容性报告
│   ├── 03-database-adapter.md          # 数据库适配层说明
│   ├── 06-deployment.md                # 部署手册
│   └── 09-testing.md                   # 自动化集成测试报告
├── public/
│   └── index.html                      # 单页应用 (Vue 3 + Element Plus + Quill)
├── storage/
│   └── attachments/                    # 私有附件存储目录
├── tests/
│   └── SystemIntegrationTest.php       # 系统端到端集成测试脚本
├── windows.php                         # Windows 环境启动入口
└── README.md                           # 项目说明文档
```

---

## 🔒 安全规范

1. **凭证与数据隔离**：数据库连接密码绝不返回前端；所有 SQL 采用参数化预编译绑定，杜绝 SQL 注入。
2. **BCC 密送防泄露**：收件人详情查询在服务层严格按权限过滤，接收方绝对无法获取 BCC 密送人信息。
3. **附件私有化**：附件存储在非公开私有目录，仅发件人或收件人在有效登录授权下通过控制器流式下载，严格防范路径穿越。
