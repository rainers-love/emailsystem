# 统一数据库适配层设计说明

- **设计规范**：基于企业架构要求，分离业务层与具体数据库方言
- **核心组件**：
  - `DatabaseManager`: 统一管理连接配置、PDO 单例与切换上下文
  - `DatabaseAdapterInterface`: 标准化方言接口（标识符安全引用、Schema 解析、DDL 生成、CRUD、事务、自增重置）
  - `MySqlAdapter`: MySQL 8.0 专属方言适配器（反引号转义、`AUTO_INCREMENT` 处理、索引定义）
  - `KingbaseAdapter`: 人大金仓 KingbaseES / PG 模式专属方言适配器（双引号转义、`BIGSERIAL/SERIAL` 映射、索引及 `setval` 序列维护）
  - `SchemaDefinition`: 独立于特定数据库的统一 Schema 描述定义，支撑双库双向生成与迁移

---

## 字段映射与方言转换规则

| 统一通用类型 | MySQL 8.0 映射 | KingbaseES (KES) 映射 | 转换说明 |
| :--- | :--- | :--- | :--- |
| `int(11) AUTO_INCREMENT` | `INT NOT NULL AUTO_INCREMENT` | `SERIAL PRIMARY KEY` | 自动处理主键与序列自增 |
| `bigint(20) AUTO_INCREMENT` | `BIGINT NOT NULL AUTO_INCREMENT`| `BIGSERIAL PRIMARY KEY` | 邮件主表/收件表采用大整型 |
| `varchar(N)` | `VARCHAR(N)` | `VARCHAR(N)` | 字符串长度保持无损对应 |
| `text` | `TEXT` | `TEXT` | 邮件正文与长文本统一映射为 TEXT |
| `tinyint(1)` | `TINYINT(1)` | `BOOLEAN` | 布尔状态统一映射 |
| `datetime` | `DATETIME` | `TIMESTAMP` | 时间格式标准化 |
| `DEFAULT CURRENT_TIMESTAMP` | `DEFAULT CURRENT_TIMESTAMP` | `DEFAULT CURRENT_TIMESTAMP` | 支持通用时间戳缺省 |

所有业务代码严禁手写 MySQL 独有语法，统一经由 `app\database\DatabaseManager::getAdapter()` 调用。
