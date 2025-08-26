# Cap-Pow Server for PHP

## 基本介绍

本项目由 [初春网络](https://www.cv0.cn) 小森开发，在 [雾创岛](https://www.tr0.cn) 发布，基于[Cap-Pow](https://github.com/tiagorangel1/cap)项目的一个分支。


弥补了Cap-Pow Server官方版本不支持世界上最好的语言PHP的遗憾。

**原Cap-Pow项目地址：**
Github：[https://github.com/tiagorangel1/cap](https://github.com/tiagorangel1/cap)
官网：[https://capjs.js.org](https://capjs.js.org)

**本项目Dome**
[]()

## 使用说明

**服务端环境**
<ul>
    <li>推荐使用 Nginx</li>
    <li>推荐PHP版本：8.0+</li>
    <li>数据库使用SQLite</li>
</ul>

**安装**
<ol>
<li>下载Cap-Pow Server for PHP源码</li>
<li>上传至网站目录</li>
<li>配置伪静态

```Nginx
    location / {
        try_files $uri $uri/ $uri.php?$query_string;
        if ($request_filename ~* .*\.php$) {
            return 403;
        }
    }
```

</li>
<li>将Cap-Pow接口换成您的Cap-Pow Server for PHP

```html
    <script src="https://cdn.jsdelivr.net/npm/@cap.js/widget"></script>

    <cap-widget
        id="cap"
        data-cap-api-endpoint="https://<your cap endpoint>"
    ></cap-widget>
```
</li>
<li>恭喜你，您已经成功安装并配置好了Cap-Pow！</li>
</ol>

## 其它

您可以任意的修改此项目、分支、发布。

如果您不想自己部署或有更高的安全需求，您也可以使用我的另外一个分支：[One-Pow](https://cha.eta.im)

**One-Pow 优化项目**
<ol>
<li>更新了更好看的组件UI和交互动画</li>
<li>FNV-1a 算法由32位换为128位</li>
<li>将token参与POW运算，增强重放攻击防护</li>
<li>新增收集行为数据分析，具备一定的人机识别访问</li>
<li>新增风险分析返回不同的计算难度</li>
<li>新增频繁请求拦截</li>
<li>轻量级的机器学习模型（行为风险得分+机器学习评分进行综合评分）</li>
<li>自动标记高风险IP</li>
</ol>

**One-Pow官网：**[https://cha.eta.im](https://cha.eta.im)
