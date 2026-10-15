# 企业内部邮件系统部署与维护指南

## 1. 系统要求与环境准备

- **操作系统**：Linux (CentOS 7/8, Ubuntu 20.04+, Rocky Linux / 国产麒麟 KylinOS / 统信 UOS) 或 Windows Server
- **PHP**：PHP 8.0 及以上版本
  - 必需扩展：`pdo_mysql`, `pdo_pgsql` (用于人大金仓对接), `fileinfo`, `mbstring`, `openssl`, `json`, `gd`
- **数据库**：
  - 默认：MySQL 8.0 / 5.7+
  - 国产化：人大金仓 KingbaseES V8 / V9 (启用兼容 PostgreSQL 协议模式)

---

## 2. 安装与部署步骤

### 2.1 依赖安装
在项目根目录下执行：
```bash
composer install --no-dev -o
```

### 2.2 数据库初始化
配置 [`config/database.php`](file:///g:/pro/zfc/emailstsrem/config/database.php) 中的数据库连接信息，或通过环境变量设置：
```bash
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=email_system
DB_USERNAME=root
DB_PASSWORD=your_password
```
执行版本化数据表迁移与初始基础种子数据导入：
```bash
php bin/migrate.php
```

### 2.3 启动服务
- **Linux 生产环境**（常驻守护模式）：
  ```bash
  php start.php start -d
  ```
- **Windows 本地开发/调试环境**：
  ```bash
  php windows.php
  # 或直接双击 windows.bat
  ```

访问地址：`http://127.0.0.1:8787`

---

## 3. 初始账号与权限

| 账号 | 默认密码 | 角色 | 权限说明 |
| :--- | :--- | :--- | :--- |
| `admin` | `123456` | 系统管理员 (`admin`) | 邮件业务、组织通讯录管理 |
| `dbadmin` | `123456` | 数据库管理员 (`db_admin`) | 专享人大金仓迁移、Schema预览、切换运维权限 |
| `zhangsan` | `123456` | 普通员工 (`user`) | 收发内部邮件、通讯录查看 |
| `lisi` | `123456` | 普通员工 (`user`) | 收发内部邮件、通讯录查看 |

---

## 4. 人大金仓 KingbaseES 迁移与切换指南

1. **结构转换预览**：登录 `dbadmin` 账号进入“数据库管理与迁移中心”，查阅目标 KingbaseES DDL 生成结果。
2. **连接连通性验证**：输入目标服务器金仓实例 IP、端口（默认 54321）及账密，执行连通性与版本探测。
3. **数据迁移与校验**：点击“发起迁移”，系统自动在后台执行任务注册、逐表结构同步、数据分批导入、序列纠偏与记录数一致性校验。
4. **安全切换**：进入系统维护窗口，停止业务写入，完成最终数据对齐后，更新活动数据库配置并重启守护进程。
