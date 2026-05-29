# Babel Arcaea Code

统一 Prism.js + Mermaid 渲染引擎。本地化资源，无 CDN 依赖。

## 功能

- **Prism.js** 本地化（核心 + 20 语言 + toolbar + copy + line-numbers + autoloader）
- **Mermaid** 本地化（ESM 模块，版本锁定 11.15.0）
- **PHP filter** 在 `the_content` 中提前替换 mermaid 代码块，Prism 永远看不到
- **PJAX 支持**（Sakurairo）
- **禁用 Sakurairo 自带 Prism**（可配置）
- **GitHub 自动更新**（PUC library）

## 安装

```bash
cd /var/www/html/wp-content/plugins/
git clone https://github.com/AKCX2002/babel-arcaea-code.git
```

后台 → 插件 → 启用 → 设置 → Arcaea Code。

## 设置

| 选项 | 推荐值 |
|------|--------|
| 总开关 | 开 |
| Prism.js | 开 |
| Mermaid | 开 |
| 禁用 Sakurairo Prism | 开 |
| 行号 | 开 |
| 复制按钮 | 开 |
| Prism 主题 | Arcaea Dark |

## 支持的语言

c, cpp, bash, python, json, yaml, cmake, makefile, dart,
javascript, typescript, rust, go, php, markdown, css, diff,
ini, toml, xml (+ autoloader 按需加载其他语言)

## License

GPL-2.0-or-later
