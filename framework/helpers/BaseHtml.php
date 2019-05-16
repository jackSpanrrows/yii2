<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\helpers;

use Yii;
use yii\base\InvalidArgumentException;
use yii\base\Model;
use yii\db\ActiveRecordInterface;
use yii\validators\StringValidator;
use yii\web\Request;

/**
 * BaseHtml 为 [[Html]] 提供了具体的实现。
 *
 * 不要使用 BaseHtml 类。使用 [[Html]] 类来替代。
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class BaseHtml
{
    /**
     * @var string 用于属性名称验证的正则表达式。
     * @since 2.0.12
     */
    public static $attributeRegex = '/(^|.*\])([\w\.\+]+)(\[.*|$)/u';
    /**
     * @var array 空元素列表（element name => 1）
     * @see http://www.w3.org/TR/html-markup/syntax.html#void-element
     */
    public static $voidElements = [
        'area' => 1,
        'base' => 1,
        'br' => 1,
        'col' => 1,
        'command' => 1,
        'embed' => 1,
        'hr' => 1,
        'img' => 1,
        'input' => 1,
        'keygen' => 1,
        'link' => 1,
        'meta' => 1,
        'param' => 1,
        'source' => 1,
        'track' => 1,
        'wbr' => 1,
    ];
    /**
     * @var array 标记中属性的首选顺序。这主要由 [[renderTagAttributes()]]
     * 影响属性的顺序。
     */
    public static $attributeOrder = [
        'type',
        'id',
        'class',
        'name',
        'value',

        'href',
        'src',
        'srcset',
        'form',
        'action',
        'method',

        'selected',
        'checked',
        'readonly',
        'disabled',
        'multiple',

        'size',
        'maxlength',
        'width',
        'height',
        'rows',
        'cols',

        'alt',
        'title',
        'rel',
        'media',
    ];
    /**
     * @var array 当其值为数组类型时应特别处理的标记属性列表。
     * 特别地，如果 `data` 属性的值为 `['name' => 'xyz', 'age' => 13]`，
     * 将生成两个属性而不是一个：`data-name="xyz" data-age="13"`。
     * @since 2.0.3
     */
    public static $dataAttributes = ['data', 'data-ng', 'ng'];


    /**
     * 将特殊字符编码为 HTML 实体。
     * 这个 [[\yii\base\Application::charset|application charset]] 将用于编码。
     * @param string $content 编码内容
     * @param bool $doubleEncode 是否对 `$content` 中的 HTML 实体进行编码。如果是 false，
     * `$content` 中的HTML实体将不会进一步编码。
     * @return string 编码内容
     * @see decode()
     * @see http://www.php.net/manual/en/function.htmlspecialchars.php
     */
    public static function encode($content, $doubleEncode = true)
    {
        return htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, Yii::$app ? Yii::$app->charset : 'UTF-8', $doubleEncode);
    }

    /**
     * 将特殊的 HTML 实体解码回相应的字符。
     * 这与 [[encode()]] 相反。
     * @param string $content 要解码的内容
     * @return string 解码内容
     * @see encode()
     * @see http://www.php.net/manual/en/function.htmlspecialchars-decode.php
     */
    public static function decode($content)
    {
        return htmlspecialchars_decode($content, ENT_QUOTES);
    }

    /**
     * 生成完整的 HTML 标记。
     * @param string|bool|null $name 标记名称。如果 $name 是 `null` 或者 `false`，相应的内容将在不带任何标记的情况下渲染。
     * @param string $content 要在开始和结束标记之间包含的内容。它将不会是 HTML 编码。
     * 如果来自最终用户，你应该考虑 [[encode()]] 它可以防止 XSS 攻击。
     * @param array $options HTML 标签属性（HTML 选项）就键值对而言。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果这个值不存在，它将不渲染相应的属性。
     *
     * 例如当使用 `['class' => 'my-class', 'target' => '_blank', 'value' => null]`
     * 它将导致 html 属性渲染如下：`class="my-class" target="_blank"`。
     *
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     *
     * @return string 生成 HTML 标记
     * @see beginTag()
     * @see endTag()
     */
    public static function tag($name, $content = '', $options = [])
    {
        if ($name === null || $name === false) {
            return $content;
        }
        $html = "<$name" . static::renderTagAttributes($options) . '>';
        return isset(static::$voidElements[strtolower($name)]) ? $html : "$html$content</$name>";
    }

    /**
     * 生成开始标记。
     * @param string|bool|null $name 标记名称。如果 $name 是 `null` 或者 `false`，相应的内容将在不带任何标记的情况下渲染。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这个值将使用 [[encode()]] 进行 HTML 编码。
     * 如果这个值不存在，它将不渲染相应的属性。
     * 关于属性是如何被渲染的细节请参考 [[renderTagAttributes()]]。
     * @return string 生成开始标记
     * @see endTag()
     * @see tag()
     */
    public static function beginTag($name, $options = [])
    {
        if ($name === null || $name === false) {
            return '';
        }

        return "<$name" . static::renderTagAttributes($options) . '>';
    }

    /**
     * 生成结束标记。
     * @param string|bool|null $name 标记名称。如果 $name 是 `null` 或者 `false`，相应的内容将在不带任何标记的情况下渲染。
     * @return string 生成结束标记
     * @see beginTag()
     * @see tag()
     */
    public static function endTag($name)
    {
        if ($name === null || $name === false) {
            return '';
        }

        return "</$name>";
    }

    /**
     * 生成样式标记。
     * @param string $content 样式内容
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果值不存在，它将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     * @return string 生成样式标记
     */
    public static function style($content, $options = [])
    {
        return static::tag('style', $content, $options);
    }

    /**
     * 生成脚本标记。
     * @param string $content 脚本内容
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果值不存在，它将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     * @return string 生成脚本标记
     */
    public static function script($content, $options = [])
    {
        return static::tag('script', $content, $options);
    }

    /**
     * 生成引用外部 CSS 文件的链接标记。
     * @param array|string $url 外部 CSS 文件的 URL。此参数将由 [[Url::to()]] 处理。
     * @param array $options 以键值对表示的标记选项。以下选项是专门处理的：
     *
     * - condition：为 IE 指定的条件注释，例如，`lt IE 9`。
     *   当前指定的，生成的 `link` 标记将使用注释封闭。
     *   这主要是用于支持 IE 旧版本浏览器。
     * - noscript：如果设置为 true，`link` 标签会被包裹进 `<noscript>` 标记中。
     *
     * 其余选项将渲染为结果链接标记的属性。
     * 这些值将使用 [[encode()]] 进行 HTML 编码。如果这个值不存在，它将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     * @return string 生成的链接标记
     * @see Url::to()
     */
    public static function cssFile($url, $options = [])
    {
        if (!isset($options['rel'])) {
            $options['rel'] = 'stylesheet';
        }
        $options['href'] = Url::to($url);

        if (isset($options['condition'])) {
            $condition = $options['condition'];
            unset($options['condition']);
            return self::wrapIntoCondition(static::tag('link', '', $options), $condition);
        } elseif (isset($options['noscript']) && $options['noscript'] === true) {
            unset($options['noscript']);
            return '<noscript>' . static::tag('link', '', $options) . '</noscript>';
        }

        return static::tag('link', '', $options);
    }

    /**
     * 生成引用外部 JavaScript 文件的脚本标记。
     * @param string $url 外部 JavaScript 文件的 URL。此参数将由 [[Url::to()]] 处理。
     * @param array $options 以键值对表示的标记选项。以下选项是专门处理的：
     *
     * - condition：为 IE 指定的条件注释，例如，`lt IE 9`。
     *   当前指定的，生成的 `script` 标记 将使用注释封闭。
     *   这主要是用于支持 IE 旧版本浏览器。
     *
     * 其余选项将渲染为结果脚本标记的属性。
     * 这些值将使用 [[encode()]] 进行 HTML 编码。如果这个值不存在，它将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     * @return string 生成脚本标记
     * @see Url::to()
     */
    public static function jsFile($url, $options = [])
    {
        $options['src'] = Url::to($url);
        if (isset($options['condition'])) {
            $condition = $options['condition'];
            unset($options['condition']);
            return self::wrapIntoCondition(static::tag('script', '', $options), $condition);
        }

        return static::tag('script', '', $options);
    }

    /**
     * 将给定的内容包装成 IE 的条件注释，例如，`lt IE 9`。
     * @param string $content 原始 HTML 内容。
     * @param string $condition 条件字符串。
     * @return string 生成 HTML。
     */
    private static function wrapIntoCondition($content, $condition)
    {
        if (strpos($condition, '!IE') !== false) {
            return "<!--[if $condition]><!-->\n" . $content . "\n<!--<![endif]-->";
        }

        return "<!--[if $condition]>\n" . $content . "\n<![endif]-->";
    }

    /**
     * 生成包含 CSRF 令牌信息的元标记。
     * @return string 生成的元标记
     * @see Request::enableCsrfValidation
     */
    public static function csrfMetaTags()
    {
        $request = Yii::$app->getRequest();
        if ($request instanceof Request && $request->enableCsrfValidation) {
            return static::tag('meta', '', ['name' => 'csrf-param', 'content' => $request->csrfParam]) . "\n    "
                . static::tag('meta', '', ['name' => 'csrf-token', 'content' => $request->getCsrfToken()]) . "\n";
        }

        return '';
    }

    /**
     * 生成表单开始标记。
     * @param array|string $action 表单操作 URL。此参数将由 [[Url::to()]] 处理。
     * @param string $method 表格提交方法，诸如 "post"，"get"，"put"，"delete"（不区分大小写）。
     * 由于大多数浏览器只支持 "post" 和 "get"，如果有其他方法，他们会
     * 使用 "post" 模拟这些方法，并添加一个包含实际方法类型的隐藏输入。
     * 请查看 [[\yii\web\Request::methodParam]] 获取更多详情。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果这个值不存在，它将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     *
     * 指定的选项：
     *
     *  - `csrf`：是否生成 CSRF 隐藏输入。默认为 true。
     *
     * @return string 生成的表单开始标记。
     * @see endForm()
     */
    public static function beginForm($action = '', $method = 'post', $options = [])
    {
        $action = Url::to($action);

        $hiddenInputs = [];

        $request = Yii::$app->getRequest();
        if ($request instanceof Request) {
            if (strcasecmp($method, 'get') && strcasecmp($method, 'post')) {
                // simulate PUT, DELETE, etc. via POST
                $hiddenInputs[] = static::hiddenInput($request->methodParam, $method);
                $method = 'post';
            }
            $csrf = ArrayHelper::remove($options, 'csrf', true);

            if ($csrf && $request->enableCsrfValidation && strcasecmp($method, 'post') === 0) {
                $hiddenInputs[] = static::hiddenInput($request->csrfParam, $request->getCsrfToken());
            }
        }

        if (!strcasecmp($method, 'get') && ($pos = strpos($action, '?')) !== false) {
            // query parameters in the action are ignored for GET method
            // we use hidden fields to add them back
            foreach (explode('&', substr($action, $pos + 1)) as $pair) {
                if (($pos1 = strpos($pair, '=')) !== false) {
                    $hiddenInputs[] = static::hiddenInput(
                        urldecode(substr($pair, 0, $pos1)),
                        urldecode(substr($pair, $pos1 + 1))
                    );
                } else {
                    $hiddenInputs[] = static::hiddenInput(urldecode($pair), '');
                }
            }
            $action = substr($action, 0, $pos);
        }

        $options['action'] = $action;
        $options['method'] = $method;
        $form = static::beginTag('form', $options);
        if (!empty($hiddenInputs)) {
            $form .= "\n" . implode("\n", $hiddenInputs);
        }

        return $form;
    }

    /**
     * 生成的表单结束标记。
     * @return string 生成的标记
     * @see beginForm()
     */
    public static function endForm()
    {
        return '</form>';
    }

    /**
     * 生成超链接标记。
     * @param string $text 链接主体。它没有被 HTML 编码。
     * 因此您可以传递 HTML 代码，如图像标记。如果这是来自最终用户，
     * 您应该考虑 [[encode()]] 它可以防止 XSS 攻击。
     * @param array|string|null $url 这个 URL 通过超链接标记。此参数将由 [[Url::to()]] 处理
     * 并将用于 "href" 属性的标记。如果这个参数值为 null，
     * "href" 属性不会被生成。
     *
     * 如果你想使用一个绝对地址可以调用 [[Url::to()]] 自己，将 URL 传递给这个方法之前，
     * 像这样：
     *
     * ```php
     * Html::a('link text', Url::to($url, true))
     * ```
     *
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果这个值不存在，它将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     * @return string 生成的超链接
     * @see \yii\helpers\Url::to()
     */
    public static function a($text, $url = null, $options = [])
    {
        if ($url !== null) {
            $options['href'] = Url::to($url);
        }

        return static::tag('a', $text, $options);
    }

    /**
     * 生成 mailto 超链接。
     * @param string $text 链接主体。它没有被 HTML 编码。
     * 因此你可以传递 HTML 代码，诸如 image 标记。如果这是来自最终用户，
     * 你应该考虑 [[encode()]] 它可以防止 XSS 攻击。
     * @param string $email email 地址。如果这个值不存在，
     * 第一个参数 (链接体) 被处理为 email 地址使用。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果这个值不存在，将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     * @return string the 生成 mailto 链接。
     */
    public static function mailto($text, $email = null, $options = [])
    {
        $options['href'] = 'mailto:' . ($email === null ? $text : $email);
        return static::tag('a', $text, $options);
    }

    /**
     * 生成 image 标签。
     * @param array|string $src 指定的 image 地址。此参数将由 [[Url::to()]] 处理。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果值不存在，它将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     *
     * 自从 2.0.12 以上版本可以将 `srcset` 选项作为数组传递，
     * 其中键是描述符，值是 URL。所有的 URLs 将通过 [[Url::to()]] 处理。
     * @return string 生成 image 标签。
     */
    public static function img($src, $options = [])
    {
        $options['src'] = Url::to($src);

        if (isset($options['srcset']) && is_array($options['srcset'])) {
            $srcset = [];
            foreach ($options['srcset'] as $descriptor => $url) {
                $srcset[] = Url::to($url) . ' ' . $descriptor;
            }
            $options['srcset'] = implode(',', $srcset);
        }

        if (!isset($options['alt'])) {
            $options['alt'] = '';
        }

        return static::tag('img', '', $options);
    }

    /**
     * 生成 label 标记。
     * @param string $content label 文本。它没有被 HTML 编码。
     * 因此你可以传递 HTML 代码，诸如 image 标记。如果这是来自最终用户，
     * 你应该考虑 [[encode()]] 它可以防止 XSS 攻击。
     * @param string $for 与此标签关联的 HTML 元素的 ID。
     * 如果这是 null，"for" 属性不会被生成。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果这个值不存在，它将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     * @return string 生成指定的 label 标签
     */
    public static function label($content, $for = null, $options = [])
    {
        $options['for'] = $for;
        return static::tag('label', $content, $options);
    }

    /**
     * 生成 button 标签。
     * @param string $content 包含在 button 标记中的内容。它没有被 HTML 编码。
     * 因此你可以传递 HTML 代码，诸如 image 标记。如果这是来自最终用户，
     * 你应该考虑 [[encode()]] 它可以防止 XSS 攻击。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果这个值不存在，它将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     * @return string 生成 button 标签
     */
    public static function button($content = 'Button', $options = [])
    {
        if (!isset($options['type'])) {
            $options['type'] = 'button';
        }

        return static::tag('button', $content, $options);
    }

    /**
     * 生成一个 submit 按钮标签。
     *
     * 命名表单元素（如 submit 按钮）时要小心。根据文档 [jQuery documentation](https://api.jquery.com/submit/)
     * 有一些保留名称可能导致冲突，比如 `submit`，`length'，或者 `method`。
     *
     * @param string $content 包含在 button 标记中的内容。它没有被 HTML 编码。
     * 因此你可以传递 HTML 代码，诸如 image 标记。如果这是来自最终用户，
     * 你应该考虑 [[encode()]] 它可以防止 XSS 攻击。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果这个值不存在，将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     * @return string 生成 submit 按钮标签
     */
    public static function submitButton($content = 'Submit', $options = [])
    {
        $options['type'] = 'submit';
        return static::button($content, $options);
    }

    /**
     * 生成 reset 按钮标签。
     * @param string $content 包含在 button 标记中的内容。它没有被 HTML 编码。
     * 因此你可以传递 HTML 代码，诸如 image 标记。
     * 如果这是来自最终用户，你应该考虑 [[encode()]] 它可以防止 XSS 攻击。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果这个值不存在，它将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     * @return string 生成 reset 按钮标签
     */
    public static function resetButton($content = 'Reset', $options = [])
    {
        $options['type'] = 'reset';
        return static::button($content, $options);
    }

    /**
     * 生成给定类型的 input 类型。
     * @param string $type 类型属性。
     * @param string $name 属性名称。如果它是 null，不会生成 name 属性。
     * @param string $value 值属性。如果它是 null，不会生成值属性。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果这个值不存在，它将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     * @return string 生成 input 标签
     */
    public static function input($type, $name = null, $value = null, $options = [])
    {
        if (!isset($options['type'])) {
            $options['type'] = $type;
        }
        $options['name'] = $name;
        $options['value'] = $value === null ? null : (string) $value;
        return static::tag('input', '', $options);
    }

    /**
     * 生成 input 按钮。
     * @param string $label 值属性。如果这个值不存在，它将不渲染相应的属性。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果这个值不存在，它将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     * @return string 生成 button 标签
     */
    public static function buttonInput($label = 'Button', $options = [])
    {
        $options['type'] = 'button';
        $options['value'] = $label;
        return static::tag('input', '', $options);
    }

    /**
     * 生成提交 input 按钮。
     *
     * 命名表单元素（如 submit 按钮）时要小心。根据文档 [jQuery documentation](https://api.jquery.com/submit/) there
     * 有一些保留名称可能导致冲突，比如 `submit`，`length'，或者 `method`。
     *
     * @param string $label 值属性。如果这个值不存在，它将不渲染相应的属性。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果这个值不存在，它将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     * @return string 生成按钮标签
     */
    public static function submitInput($label = 'Submit', $options = [])
    {
        $options['type'] = 'submit';
        $options['value'] = $label;
        return static::tag('input', '', $options);
    }

    /**
     * 生成重置输入按钮。
     * @param string $label 值属性。如果这个值不存在，它将不渲染相应的属性。
     * @param array $options 按钮标记的属性。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 值为空的属性将被忽略，并且不会放入返回的标记中。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     * @return string 生成按钮标签
     */
    public static function resetInput($label = 'Reset', $options = [])
    {
        $options['type'] = 'reset';
        $options['value'] = $label;
        return static::tag('input', '', $options);
    }

    /**
     * 生成文本输入字段。
     * @param string $name 名称属性。
     * @param string $value 值属性。如果它是 null，这个值属性不会被生成。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果这个值不存在，它将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     * @return string 生成文本输入标签
     */
    public static function textInput($name, $value = null, $options = [])
    {
        return static::input('text', $name, $value, $options);
    }

    /**
     * 生成隐藏输入字段。
     * @param string $name 名称属性。
     * @param string $value 值属性。如果它是 null，这个值属性不会被生成。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果这个值不存在，它将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     * @return string 生成隐藏输入标签
     */
    public static function hiddenInput($name, $value = null, $options = [])
    {
        return static::input('hidden', $name, $value, $options);
    }

    /**
     * 生成密码输入字段。
     * @param string $name 名称属性。
     * @param string $value 值属性。如果它是 null，这个值属性不会被生成。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果这个值不存在，它将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     * @return string 生成密码输入标签
     */
    public static function passwordInput($name, $value = null, $options = [])
    {
        return static::input('password', $name, $value, $options);
    }

    /**
     * 生成文件输入字段。
     * 使用文件输入字段，应将封闭表单的 "enctype" 属性设置为 "multipart/form-data"。
     * 提交表单后，
     * 可以通过 $_FILES[$name] 获取上传的文件信息 (see PHP documentation)。
     * @param string $name 名称属性。
     * @param string $value 值属性。如果它是 null，这个值属性不会被生成。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果这个值不存在，它将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     * @return string 生成文件输入标签
     */
    public static function fileInput($name, $value = null, $options = [])
    {
        return static::input('file', $name, $value, $options);
    }

    /**
     * 生成文本域输入。
     * @param string $name 输入名称
     * @param string $value 输入值。请注意它将使用 [[encode()]] 进行编码。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果这个值不存在，它将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     * 可识别以下特殊选项：
     *
     * - `doubleEncode`：是否对 `$value` 中的 HTML 实体进行双重编码。如果是 `false`，`$value` 中的 HTML 实体将不被进一步编码。
     *   此选项自 2.0.11 版起可用。
     *
     * @return string 生成文本域标签
     */
    public static function textarea($name, $value = '', $options = [])
    {
        $options['name'] = $name;
        $doubleEncode = ArrayHelper::remove($options, 'doubleEncode', true);
        return static::tag('textarea', static::encode($value, $doubleEncode), $options);
    }

    /**
     * 生成一个单选按钮输入。
     * @param string $name 名称属性。
     * @param bool $checked 是否应检查单选按钮。
     * @param array $options 以键值对表示的标记选项。
     * 有关允许的属性的详细信息请参考 [[booleanInput()]]。
     *
     * @return string 生成的单选按钮标记
     */
    public static function radio($name, $checked = false, $options = [])
    {
        return static::booleanInput('radio', $name, $checked, $options);
    }

    /**
     * 生成复选框输入。
     * @param string $name 名称属性。
     * @param bool $checked 是否应选中该复选框。
     * @param array $options 以键值对表示的标记选项。
     * 有关允许的属性的详细信息请参考 [[booleanInput()]]。
     *
     * @return string 生成复选框标签
     */
    public static function checkbox($name, $checked = false, $options = [])
    {
        return static::booleanInput('checkbox', $name, $checked, $options);
    }

    /**
     * 生成布尔输入。
     * @param string $type 输入类型。这可以是 `radio` 或者 `checkbox`。
     * @param string $name 名称属性。
     * @param bool $checked 是否应选中该复选框。
     * @param array $options 以键值对表示的标记选项。以下选项是专门处理的：
     *
     * - uncheck：字符串，与复选框的取消选中状态关联的值。
     *   当该属性存在时，将生成一个隐藏的输入，以便如果未选中复选框并提交，
     *   此属性的值仍将通过隐藏输入提交到服务器。
     * - label：字符串，复选框旁边显示的标签。它不会被 HTML 编码。
     *   因此你可以传递 HTML 代码，诸如 image 标记。如果这是来自最终用户，你应该考虑 [[encode()]] 它可以防止 XSS 攻击。
     *   当指定此选项时，复选框将由标签标记闭合。
     * - labelOptions：数组，给 label 标签的 HTML 属性。除非设置 "label" 选项，否则不要设置此选项。
     *
     * 其余选项将渲染为结果复选框标记的属性。
     * 这些值将使用 [[encode()]] 进行 HTML 编码。如果这个值不存在，它将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     *
     * @return string 生成复选框标签
     * @since 2.0.9
     */
    protected static function booleanInput($type, $name, $checked = false, $options = [])
    {
        // 'checked' option has priority over $checked argument
        if (!isset($options['checked'])) {
            $options['checked'] = (bool) $checked;
        }
        $value = array_key_exists('value', $options) ? $options['value'] : '1';
        if (isset($options['uncheck'])) {
            // add a hidden field so that if the checkbox is not selected, it still submits a value
            $hiddenOptions = [];
            if (isset($options['form'])) {
                $hiddenOptions['form'] = $options['form'];
            }
            // make sure disabled input is not sending any value
            if (!empty($options['disabled'])) {
                $hiddenOptions['disabled'] = $options['disabled'];
            }
            $hidden = static::hiddenInput($name, $options['uncheck'], $hiddenOptions);
            unset($options['uncheck']);
        } else {
            $hidden = '';
        }
        if (isset($options['label'])) {
            $label = $options['label'];
            $labelOptions = isset($options['labelOptions']) ? $options['labelOptions'] : [];
            unset($options['label'], $options['labelOptions']);
            $content = static::label(static::input($type, $name, $value, $options) . ' ' . $label, null, $labelOptions);
            return $hidden . $content;
        }

        return $hidden . static::input($type, $name, $value, $options);
    }

    /**
     * 生成下拉列表。
     * @param string $name 输入名称
     * @param string|array|null $selection 选定的值。用于单个选择的字符串或用于多个选择的数组。
     * @param array $items 选项数据项。数组键是选项值，
     * 数组值是相应的选项标签。数组也可以嵌套 (比如某些数组值也是数组)。
     * 对于每个子数组，将生成一个选项组，其标签是与子数组关联的键。
     * 如果您有数据模型列表，
     * 你可以使用 [[\yii\helpers\ArrayHelper::map()]] 将其转换为上述格式。
     *
     * 请注意，这些值和标签将通过该方法自动 HTML 编码，
     * 标签中的空白空间也将被 HTML 编码。
     * @param array $options 以键值对表示的标记选项。以下选项是专门处理的：
     *
     * - prompt：字符串，作为第一个选项显示的提示文本。从 2.0.11
     *   起你可以使用数组覆盖该值并设置其他标记属性：
     *
     *   ```php
     *   ['text' => 'Please select', 'options' => ['value' => 'none', 'class' => 'prompt', 'label' => 'Select']],
     *   ```
     *
     * - options：数组，选择选项标记的属性。数组键必须是有效的选项值，
     *   数组值是对应选项标记的额外属性。举例，
     *
     *   ```php
     *   [
     *       'value1' => ['disabled' => true],
     *       'value2' => ['label' => 'value 2'],
     *   ];
     *   ```
     *
     * - groups：数组，optgroup 标记的属性。它的结构类似于 'options'，
     *   除了数组键表示在 $items 中指定的 optgroup 标签。
     * - encodeSpaces：布尔，是否将选项提示和选项值中的空格编码为 `&nbsp;` 字符。
     *   默认为 false。
     * - encode：布尔，是否对选项提示和选项值字符进行编码。
     *   默认是 `true`。此选项从 2.0.3 开始提供。
     *
     * 其余选项将渲染为结果标记的属性。
     * 这些值将使用 [[encode()]] 进行 HTML 编码。如果这个值不存在，它将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     *
     * @return string 生成下拉列表标签
     */
    public static function dropDownList($name, $selection = null, $items = [], $options = [])
    {
        if (!empty($options['multiple'])) {
            return static::listBox($name, $selection, $items, $options);
        }
        $options['name'] = $name;
        unset($options['unselect']);
        $selectOptions = static::renderSelectOptions($selection, $items, $options);
        return static::tag('select', "\n" . $selectOptions . "\n", $options);
    }

    /**
     * 生成列表框。
     * @param string $name 输入名称
     * @param string|array|null $selection 选定的值。用于单个选择的字符串或用于多个选择的数组。
     * @param array $items 选项数据项。数组键是选项值，
     * 数组值是对应的选项标签。数组也可以嵌套（比如某些数组值也是数组）。
     * 对于每个子数组，将生成一个选项组，其标签是与子数组关联的键。
     * 如果您有数据模型列表，
     * 你可以使用 [[\yii\helpers\ArrayHelper::map()]] 将其转换为上述格式。
     *
     * 注意，这些值和标签将通过该方法自动 HTML 编码，
     * 标签中的空白空间也将被 HTML 编码。
     * @param array $options 以键值对表示的标记选项。以下选项是专门处理的：
     *
     * - prompt：字符串，作为第一个选项显示的提示文本。从版本 2.0.11
     *   后你可以使用数组覆盖该值并设置其他标记属性：
     *
     *   ```php
     *   ['text' => 'Please select', 'options' => ['value' => 'none', 'class' => 'prompt', 'label' => 'Select']],
     *   ```
     *
     * - options：数组，选择选项标记的属性。数组键必须是有效的选项值，
     *   数组值是对应选项标记的额外属性。例如，
     *
     *   ```php
     *   [
     *       'value1' => ['disabled' => true],
     *       'value2' => ['label' => 'value 2'],
     *   ];
     *   ```
     *
     * - groups：数组，optgroup 标记的属性。它的结构类似于 'options'，
     *   除了数组键表示在 $items 中指定的 optgroup 标签之外。
     * - unselect：字符串，当没有选择任何选项时将提交的值。
     *   当设置此属性时，将生成一个隐藏字段，如果在多个模式下没有选择任何选项，
     *   我们仍然可以获得传递的未选择的值。
     * - encodeSpaces：布尔，是否在选项提示符和选项值中用 `&nbsp;` 字符编码空格。
     *   默认是 false。
     * - encode：布尔，是否对选项提示和选项值字符进行编码。
     *   默认是 `true`。此选项从 2.0.3 开始提供。
     *
     * 其余选项将渲染为结果标记的属性。
     * 这些值将使用 [[encode()]] 进行 HTML 编码。如果这个值不存在，它将不渲染相应的属性。
     * 关于属性的渲染方式详情请参考 [[renderTagAttributes()]]。
     *
     * @return string 生成列表框标记
     */
    public static function listBox($name, $selection = null, $items = [], $options = [])
    {
        if (!array_key_exists('size', $options)) {
            $options['size'] = 4;
        }
        if (!empty($options['multiple']) && !empty($name) && substr_compare($name, '[]', -2, 2)) {
            $name .= '[]';
        }
        $options['name'] = $name;
        if (isset($options['unselect'])) {
            // add a hidden field so that if the list box has no option being selected, it still submits a value
            if (!empty($name) && substr_compare($name, '[]', -2, 2) === 0) {
                $name = substr($name, 0, -2);
            }
            $hiddenOptions = [];
            // make sure disabled input is not sending any value
            if (!empty($options['disabled'])) {
                $hiddenOptions['disabled'] = $options['disabled'];
            }
            $hidden = static::hiddenInput($name, $options['unselect'], $hiddenOptions);
            unset($options['unselect']);
        } else {
            $hidden = '';
        }
        $selectOptions = static::renderSelectOptions($selection, $items, $options);
        return $hidden . static::tag('select', "\n" . $selectOptions . "\n", $options);
    }

    /**
     * 生成复选框列表。
     * 复选框列表允许多种选择，如 [[listBox()]]。
     * 因此，相应的提交值是一个数组。
     * @param string $name 每个复选框的名称属性。
     * @param string|array|null $selection 选定的值。用于单个选择的字符串或用于多个选择的数组。
     * @param array $items 用于生成复选框的数据项。
     * 数组键是复选框值，而数组值是相应的标签。
     * @param array $options 复选框列表容器标记的选项（name => config）。
     * 以下选项是专门处理的：
     *
     * - tag：字符串 |false，容器元素的标记名。假以渲染不带容器的复选框。
     *   另请参见 [[tag()]]。
     * - unselect：字符串，未选中任何复选框时应提交的值。
     *   通过设置此选项，将生成隐藏输入。
     * - disabled：布尔，是否应禁用由取消选择选项生成的隐藏输入。默认为 false。
     * - encode：布尔，是否 HTML 编码复选框标签。默认为 true。
     *   如果设置了 `item` 选项，则忽略此选项。
     * - separator：字符串，分隔项目的 HTML 代码。
     * - itemOptions：数组，使用 [[checkbox()]] 生成复选框标记的选项。
     * - item：回调，可用于自定义生成与 $items 中单个项目对应的 HTML 代码的回调。
     *   此回调的签名必须是：
     *
     *   ```php
     *   function ($index, $label, $name, $checked, $value)
     *   ```
     *
     *   其中 $index 是整个列表中复选框的零基索引；
     *   $label 是复选框的标签；并且 $name，$value 和 $checked 代表这个名字，
     *   值和复选框输入的选中状态。
     *
     * 有关如何渲染属性的详细信息请参考 [[renderTagAttributes()]]。
     *
     * @return string 生成复选框列表
     */
    public static function checkboxList($name, $selection = null, $items = [], $options = [])
    {
        if (substr($name, -2) !== '[]') {
            $name .= '[]';
        }
        if (ArrayHelper::isTraversable($selection)) {
            $selection = array_map('strval', (array)$selection);
        }

        $formatter = ArrayHelper::remove($options, 'item');
        $itemOptions = ArrayHelper::remove($options, 'itemOptions', []);
        $encode = ArrayHelper::remove($options, 'encode', true);
        $separator = ArrayHelper::remove($options, 'separator', "\n");
        $tag = ArrayHelper::remove($options, 'tag', 'div');

        $lines = [];
        $index = 0;
        foreach ($items as $value => $label) {
            $checked = $selection !== null &&
                (!ArrayHelper::isTraversable($selection) && !strcmp($value, $selection)
                    || ArrayHelper::isTraversable($selection) && ArrayHelper::isIn((string)$value, $selection));
            if ($formatter !== null) {
                $lines[] = call_user_func($formatter, $index, $label, $name, $checked, $value);
            } else {
                $lines[] = static::checkbox($name, $checked, array_merge([
                    'value' => $value,
                    'label' => $encode ? static::encode($label) : $label,
                ], $itemOptions));
            }
            $index++;
        }

        if (isset($options['unselect'])) {
            // add a hidden field so that if the list box has no option being selected, it still submits a value
            $name2 = substr($name, -2) === '[]' ? substr($name, 0, -2) : $name;
            $hiddenOptions = [];
            // make sure disabled input is not sending any value
            if (!empty($options['disabled'])) {
                $hiddenOptions['disabled'] = $options['disabled'];
            }
            $hidden = static::hiddenInput($name2, $options['unselect'], $hiddenOptions);
            unset($options['unselect'], $options['disabled']);
        } else {
            $hidden = '';
        }

        $visibleContent = implode($separator, $lines);

        if ($tag === false) {
            return $hidden . $visibleContent;
        }

        return $hidden . static::tag($tag, $visibleContent, $options);
    }

    /**
     * 生成单选按钮列表。
     * 单选按钮列表类似于复选框列表，但它只允许进行单个选择。
     * @param string $name 每个单选按钮的名称属性。
     * @param string|array|null $selection 选定的值。用于单个选择的字符串或用于多个选择的数组。
     * @param array $items 用于生成单选按钮的数据项。
     * 数组键是单选按钮值，而数组值是相应的标签。
     * @param array $options 单选按钮列表容器标记的选项（name => config）。
     * 以下选项是专门处理的：
     *
     * - tag：字符串 |false，容器元素的标记名。假以渲染不带容器的复选框。
     *   另请参见 [[tag()]]。
     * - unselect：字符串，当没有选择任何单选按钮时应提交的值。
     *   通过设置此选项，将生成隐藏输入。
     * - disabled：布尔值，是否应禁用由取消选择选项生成的隐藏输入。默认为 false。
     * - encode：布尔值，是否 HTML 编码复选框标签。默认为 true。
     *   如果设置了 `item` 选项，则忽略此选项。
     * - separator：字符串，分隔项目的 HTML 代码。
     * - itemOptions：数组，使用 [[radio()]] 生成单选按钮标记的选项。
     * - item：回调，可用于自定义生成与 $items 中单个项目对应的 HTML 代码的回调。
     *   此回调的签名必须是：
     *
     *   ```php
     *   function ($index, $label, $name, $checked, $value)
     *   ```
     *
     *   其中 $index 是整个列表中单选按钮的零基索引；
     *   $label 是单选按钮的标签；并且 $name，$value 和 $checked 代表这个名字，
     *   值和单选按钮输入的检查状态分开。
     *
     * 有关如何渲染属性的详细信息请参考 [[renderTagAttributes()]]。
     *
     * @return string 生成单选按钮列表
     */
    public static function radioList($name, $selection = null, $items = [], $options = [])
    {
        if (ArrayHelper::isTraversable($selection)) {
            $selection = array_map('strval', (array)$selection);
        }

        $formatter = ArrayHelper::remove($options, 'item');
        $itemOptions = ArrayHelper::remove($options, 'itemOptions', []);
        $encode = ArrayHelper::remove($options, 'encode', true);
        $separator = ArrayHelper::remove($options, 'separator', "\n");
        $tag = ArrayHelper::remove($options, 'tag', 'div');

        $hidden = '';
        if (isset($options['unselect'])) {
            // add a hidden field so that if the list box has no option being selected, it still submits a value
            $hiddenOptions = [];
            // make sure disabled input is not sending any value
            if (!empty($options['disabled'])) {
                $hiddenOptions['disabled'] = $options['disabled'];
            }
            $hidden =  static::hiddenInput($name, $options['unselect'], $hiddenOptions);
            unset($options['unselect'], $options['disabled']);
        }

        $lines = [];
        $index = 0;
        foreach ($items as $value => $label) {
            $checked = $selection !== null &&
                (!ArrayHelper::isTraversable($selection) && !strcmp($value, $selection)
                    || ArrayHelper::isTraversable($selection) && ArrayHelper::isIn((string)$value, $selection));
            if ($formatter !== null) {
                $lines[] = call_user_func($formatter, $index, $label, $name, $checked, $value);
            } else {
                $lines[] = static::radio($name, $checked, array_merge([
                    'value' => $value,
                    'label' => $encode ? static::encode($label) : $label,
                ], $itemOptions));
            }
            $index++;
        }
        $visibleContent = implode($separator, $lines);

        if ($tag === false) {
            return $hidden . $visibleContent;
        }

        return $hidden . static::tag($tag, $visibleContent, $options);
    }

    /**
     * 生成无序列表。
     * @param array|\Traversable $items 用于生成列表的项。每个项生成一个列表项。
     * 请注意，如果未设置 `$options['encode']` 或者 true 则项目将自动进行 HTML 编码。
     * @param array $options 单选按钮列表的选项（name => config）。支持以下选项：
     *
     * - encode：布尔值，是否对项目进行 HTML 编码。默认是 true。
     *   如果指定了 `item` 选项，则忽略此选项。
     * - separator：字符串，分隔项的 HTML 代码。默认为简单换行符（`"\n"`）。
     *   此选项自 2.0.7 版起可用。
     * - itemOptions：数组，`li` 标记的 HTML 属性。如果指定了 `item` 选项，则忽略此选项。
     * - item：回调，用于生成每个单独列表项的回调。
     *   此回调的签名必须是：
     *
     *   ```php
     *   function ($item, $index)
     *   ```
     *
     *   其中 $index 是对应于 `$item` 中的 `$item` 的数组键。
     *   回调应该返回整个列表项标记。
     *
     * 有关如何渲染属性的详细信息请参考 [[renderTagAttributes()]]。
     *
     * @return string 生成的无序列表。如果 `$items` 为空，则返回空列表标记。
     */
    public static function ul($items, $options = [])
    {
        $tag = ArrayHelper::remove($options, 'tag', 'ul');
        $encode = ArrayHelper::remove($options, 'encode', true);
        $formatter = ArrayHelper::remove($options, 'item');
        $separator = ArrayHelper::remove($options, 'separator', "\n");
        $itemOptions = ArrayHelper::remove($options, 'itemOptions', []);

        if (empty($items)) {
            return static::tag($tag, '', $options);
        }

        $results = [];
        foreach ($items as $index => $item) {
            if ($formatter !== null) {
                $results[] = call_user_func($formatter, $item, $index);
            } else {
                $results[] = static::tag('li', $encode ? static::encode($item) : $item, $itemOptions);
            }
        }

        return static::tag(
            $tag,
            $separator . implode($separator, $results) . $separator,
            $options
        );
    }

    /**
     * 生成有序列表。
     * @param array|\Traversable $items 用于生成列表的项。每个项生成一个列表项。
     * 请注意，如果 `$options['encode']` 未设置或为 true，则项目将自动进行 HTML 编码。
     * @param array $options 单选按钮列表的选项（名称=>config）。支持以下选项：
     *
     * - encode：布尔值，是否对项目进行 HTML 编码。默认为 true。
     *   如果指定了 `item` 选项，则忽略此选项。
     * - itemOptions：数组，`li` 标记的 HTML 属性。如果指定了 `item` 选项，则忽略此选项。
     * - item：回调，用于生成每个单独列表项的回调。
     *   此回调的签名必须是：
     *
     *   ```php
     *   function ($item, $index)
     *   ```
     *
     *   其中 $index 是对应于 `$item` 中的 `$item` 的数组键。
     *   回调应该返回整个列表项标记。
     *
     * 有关如何渲染属性的详细信息请参考 [[renderTagAttributes()]]。
     *
     * @return string 生成的排序列表。如果 `$items` 为空，则返回空字符串。
     */
    public static function ol($items, $options = [])
    {
        $options['tag'] = 'ol';
        return static::ul($items, $options);
    }

    /**
     * 为给定的模型属性生成标签标记。
     * 标签文本是与属性关联的标签，通过 [[Model::getAttributeLabel()]] 获得。
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式。
     * 有关属性表达式的格式请参见 [[getAttributeName()]]。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果这个值不存在，它将不渲染相应的属性。
     * 以下选项是专门处理的：
     *
     * - label：这将指定要显示的标签。注意这不会被 [[encode()|encoded]]。
     *   如果未被设置，[[Model::getAttributeLabel()]]
     *   将调用以获取要显示的标签（after encoding）。
     *
     * 有关如何渲染属性的详细信息请参考 [[renderTagAttributes()]]。
     *
     * @return string 生成的标签标记
     */
    public static function activeLabel($model, $attribute, $options = [])
    {
        $for = ArrayHelper::remove($options, 'for', static::getInputId($model, $attribute));
        $attribute = static::getAttributeName($attribute);
        $label = ArrayHelper::remove($options, 'label', static::encode($model->getAttributeLabel($attribute)));
        return static::label($label, $for, $options);
    }

    /**
     * 为给定的模型属性生成提示标记。
     * 提示文本是与属性关联的提示，通过 [[Model::getAttributeHint()]] 获得。
     * 如果无法获取提示内容，则方法将返回空字符串。
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式。
     * 有关属性表达式的格式请参见 [[getAttributeName()]]。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果这个值不存在，它将不渲染相应的属性。
     * 以下选项是专门处理的：
     *
     * - hint：这指定要显示的提示。注意这不会被 [[encode()|encoded]]。
     *   如果未被设置，[[Model::getAttributeHint()]]
     *   将调用以获取要显示的标签（without encoding）。
     *
     * 有关如何渲染属性的详细信息请参考 [[renderTagAttributes()]]。
     *
     * @return string 生成的提示标记
     * @since 2.0.4
     */
    public static function activeHint($model, $attribute, $options = [])
    {
        $attribute = static::getAttributeName($attribute);
        $hint = isset($options['hint']) ? $options['hint'] : $model->getAttributeHint($attribute);
        if (empty($hint)) {
            return '';
        }
        $tag = ArrayHelper::remove($options, 'tag', 'div');
        unset($options['hint']);
        return static::tag($tag, $hint, $options);
    }

    /**
     * 生成验证错误的摘要。
     * 如果没有验证错误，将仍然生成一个空的错误摘要标记，但它将被隐藏。
     * @param Model|Model[] $models 要显示其验证错误的模型。
     * @param array $options 以键值对表示的标记选项。以下选项是专门处理的：
     *
     * - header：字符串，错误摘要的头 HTML。如果没有设置，将使用默认提示字符串。
     * - footer：字符串，错误摘要的页脚 HTML。默认为空字符串。
     * - encode：布尔值，如果设置为 false，则不会对错误消息进行编码。默认是 `true`。
     * - showAllErrors：布尔值，如果设置为 true，则将显示每个属性的每个错误消息，
     *   否则只显示每个属性的第一条错误消息。默认是 `false`。
     *   选项从 2.0.10 开始可用。
     *
     * 其余选项将渲染为容器标记的属性。
     *
     * @return string 生成的错误摘要
     */
    public static function errorSummary($models, $options = [])
    {
        $header = isset($options['header']) ? $options['header'] : '<p>' . Yii::t('yii', 'Please fix the following errors:') . '</p>';
        $footer = ArrayHelper::remove($options, 'footer', '');
        $encode = ArrayHelper::remove($options, 'encode', true);
        $showAllErrors = ArrayHelper::remove($options, 'showAllErrors', false);
        unset($options['header']);
        $lines = self::collectErrors($models, $encode, $showAllErrors);
        if (empty($lines)) {
            // still render the placeholder for client-side validation use
            $content = '<ul></ul>';
            $options['style'] = isset($options['style']) ? rtrim($options['style'], ';') . '; display:none' : 'display:none';
        } else {
            $content = '<ul><li>' . implode("</li>\n<li>", $lines) . '</li></ul>';
        }

        return Html::tag('div', $header . $content . $footer, $options);
    }

    /**
     * 返回验证错误数组
     * @param Model|Model[] $models 要显示其验证错误的模型。
     * @param $encode 布尔值，如果设置为 false 则不会对错误消息进行编码。
     * @param $showAllErrors 布尔值，如果设置为 true，则将显示每个属性的每个错误消息，
     * 否则只显示每个属性的第一条错误消息。
     * @return 验证错误数组
     * @since 2.0.14
     */
    private static function collectErrors($models, $encode, $showAllErrors)
    {
        $lines = [];
        if (!is_array($models)) {
            $models = [$models];
        }

        foreach ($models as $model) {
            $lines = array_unique(array_merge($lines, $model->getErrorSummary($showAllErrors)));
        }

        // If there are the same error messages for different attributes, array_unique will leave gaps
        // between sequential keys. Applying array_values to reorder array keys.
        $lines = array_values($lines);

        if ($encode) {
            foreach ($lines as &$line) {
                $line = Html::encode($line);
            }
        }

        return $lines;
    }

    /**
     * 生成包含指定模型属性的第一个验证错误的标记。
     * 请注意，即使没有验证错误，此方法仍将返回空的错误标记。
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式。
     * 有关属性表达式的格式请参考 [[getAttributeName()]]。
     * @param array $options 以键值对表示的标记选项。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 如果这个值不存在，它将不渲染相应的属性。
     *
     * 以下选项是专门处理的：
     *
     * - tag：这将指定标记名。如果未设置，将使用 "div"。
     *   也可以参考 [[tag()]]。
     * - encode：布尔值，如果设置为 false 则不会对错误消息进行编码。
     * - 错误源（since 2.0.14）：\Closure|callable，将调用以获取错误消息的回调。
     *   回调的签名必须是：`function ($model, $attribute)` 并返回一个字符串。
     *   如果不设置，`$model->getFirstError()` 方法将被调用。
     *
     * 有关如何渲染属性的详细信息请参考 [[renderTagAttributes()]]。
     *
     * @return string 生成的标签标记
     */
    public static function error($model, $attribute, $options = [])
    {
        $attribute = static::getAttributeName($attribute);
        $errorSource = ArrayHelper::remove($options, 'errorSource');
        if ($errorSource !== null) {
            $error = call_user_func($errorSource, $model, $attribute);
        } else {
            $error = $model->getFirstError($attribute);
        }
        $tag = ArrayHelper::remove($options, 'tag', 'div');
        $encode = ArrayHelper::remove($options, 'encode', true);
        return Html::tag($tag, $encode ? Html::encode($error) : $error, $options);
    }

    /**
     * 为给定的模型属性生成输入标记。
     * 此方法将自动为模型属性生成 "name" 和 "value" 标记属性，
     * 除非 `$options` 中明确指定它们。
     * @param string $type 输入类型（例如 'text'，'password'）
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式。
     * 有关属性表达式的格式，请参见 [[getAttributeName()]]。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 有关如何渲染属性的详细信息请参考 [[renderTagAttributes()]]。
     * @return string 生成输入标记
     */
    public static function activeInput($type, $model, $attribute, $options = [])
    {
        $name = isset($options['name']) ? $options['name'] : static::getInputName($model, $attribute);
        $value = isset($options['value']) ? $options['value'] : static::getAttributeValue($model, $attribute);
        if (!array_key_exists('id', $options)) {
            $options['id'] = static::getInputId($model, $attribute);
        }

        static::setActivePlaceholder($model, $attribute, $options);
        self::normalizeMaxLength($model, $attribute, $options);

        return static::input($type, $name, $value, $options);
    }

    /**
     * 如果 `maxlength` 选项设置为 true 并且模型属性由字符串验证器验证，
     * 则 `maxlength` 选项的值将被 [[\yii\validators\StringValidator::max]] 处理。
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式。
     * @param array $options 以键值对表示的标记选项。
     */
    private static function normalizeMaxLength($model, $attribute, &$options)
    {
        if (isset($options['maxlength']) && $options['maxlength'] === true) {
            unset($options['maxlength']);
            $attrName = static::getAttributeName($attribute);
            foreach ($model->getActiveValidators($attrName) as $validator) {
                if ($validator instanceof StringValidator && $validator->max !== null) {
                    $options['maxlength'] = $validator->max;
                    break;
                }
            }
        }
    }

    /**
     * 为给定的模型属性生成文本输入标记。
     * 此方法将自动为模型属性生成 "name" 和 "value" 标记属性，
     * 除非 `$options` 中明确指定它们。
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式。
     * 有关属性表达式的格式，请参见 [[getAttributeName()]]。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 有关如何渲染属性的详细信息请参考 [[renderTagAttributes()]]。
     * 可识别以下特殊选项：
     *
     * - maxlength：整型 | 布尔型，当 `maxlength` 并且模型属性由字符串验证器验证时，
     *   则 `maxlength` 选项的值将被 [[\yii\validators\StringValidator::max]] 处理。
     *   这是从 2.0.3 版开始提供的。
     * - placeholder：字符串 | 布尔型，当 `placeholder` 等于 `true`，
     *   $model 中的属性标签将用作占位符（此行为在 2.0.14 版之后可用）。
     *
     * @return string 生成输入标记
     */
    public static function activeTextInput($model, $attribute, $options = [])
    {
        return static::activeInput('text', $model, $attribute, $options);
    }

    /**
     * 从模型属性标签生成占位符。
     *
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式。
     * 有关属性表达式的格式，请参见 [[getAttributeName()]]。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * @since 2.0.14
     */
    protected static function setActivePlaceholder($model, $attribute, &$options = [])
    {
        if (isset($options['placeholder']) && $options['placeholder'] === true) {
            $attribute = static::getAttributeName($attribute);
            $options['placeholder'] = $model->getAttributeLabel($attribute);
        }
    }

    /**
     * 为给定的模型属性生成隐藏的输入标记。
     * 此方法将自动为模型属性生成 "name" 和 "value" 标记属性，
     * 除非 `$options` 中明确指定它们。
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式。
     * 有关属性表达式的格式，请参见 [[getAttributeName()]]。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 有关如何渲染属性的详细信息请参考 [[renderTagAttributes()]]。
     * @return string 生成输入标记
     */
    public static function activeHiddenInput($model, $attribute, $options = [])
    {
        return static::activeInput('hidden', $model, $attribute, $options);
    }

    /**
     * 为给定的模型属性生成密码输入标记。
     * 此方法将自动为模型属性生成 "name" 和 "value" 标记属性，
     * 除非 `$options` 中明确指定它们。
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式。
     * 有关属性表达式的格式，请参见 [[getAttributeName()]]。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 有关如何渲染属性的详细信息请参考 [[renderTagAttributes()]]。
     * 可识别以下特殊选项：
     *
     * - maxlength：整型 | 布尔型，当 `maxlength` 并且模型属性由字符串验证器验证时，
     *   则 `maxlength` 选项的值将被 [[\yii\validators\StringValidator::max]] 处理。
     *   此选项自 2.0.6 版起可用。
     * - placeholder：字符串 | 布尔型，当 `placeholder` 等于 `true` 时，
     *   $model中的属性标签将用作占位符（此行为在 2.0.14 版之后可用）。
     *
     * @return string 生成输入标记
     */
    public static function activePasswordInput($model, $attribute, $options = [])
    {
        return static::activeInput('password', $model, $attribute, $options);
    }

    /**
     * 为给定的模型属性生成文件输入标记。
     * 此方法将自动为模型属性生成 "name" 和 "value" 标记属性，
     * 除非 `$options` 中明确指定它们。
     * 此外，如果在 `$options` 中定义了一组单独的 HTML 选项数组，并使用名为 `hiddenOptions` 的键，
     * 它将作为自己的 $options` 参数传递到 `activeHiddenInput` 字段。
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式。
     * 有关属性表达式的格式请参见 [[getAttributeName()]]。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 有关如何渲染属性的详细信息请参考 [[renderTagAttributes()]]。
     * 如果定义了另一组 HTML 选项数组的 `hiddenOptions` 参数，
     * 它将从 `$options` 中提取用于隐藏输入。
     * @return string 生成输入标记
     */
    public static function activeFileInput($model, $attribute, $options = [])
    {
        $hiddenOptions = ['id' => null, 'value' => ''];
        if (isset($options['name'])) {
            $hiddenOptions['name'] = $options['name'];
        }
        // make sure disabled input is not sending any value
        if (!empty($options['disabled'])) {
            $hiddenOptions['disabled'] = $options['disabled'];
        }
        $hiddenOptions = ArrayHelper::merge($hiddenOptions, ArrayHelper::remove($options, 'hiddenOptions', []));
        // Add a hidden field so that if a model only has a file field, we can
        // still use isset($_POST[$modelClass]) to detect if the input is submitted.
        // The hidden input will be assigned its own set of html options via `$hiddenOptions`.
        // This provides the possibility to interact with the hidden field via client script.
        // Note: For file-field-only model with `disabled` option set to `true` input submitting detection won't work.

        return static::activeHiddenInput($model, $attribute, $hiddenOptions)
            . static::activeInput('file', $model, $attribute, $options);
    }

    /**
     * 为给定的模型属性生成文本区域标记。
     * 模型属性值将用作文本区域中的内容。
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式。
     * 有关属性表达式的格式请参见 [[getAttributeName()]]。
     * @param array $options 以键值对表示的标记选项。
     * 这些将作为结果标记的属性渲染。这些值将使用 [[encode()]] 进行 HTML 编码。
     * 有关如何渲染属性的详细信息请参考 [[renderTagAttributes()]]。
     * 可识别以下特殊选项：
     *
     * - maxlength：整型 | 布尔型，当 `maxlength` 并且模型属性由字符串验证器验证时，
     *   则 `maxlength` 选项的值将被 [[\yii\validators\StringValidator::max]] 处理。
     *   此选项自 2.0.6 版起可用。
     * - placeholder：字符串 | 布尔型，当 `placeholder` 等于 `true` 时，
     *   $model中的属性标签将用作占位符（此行为在 2.0.14 版之后可用）。
     *
     * @return string 生成文本域标记
     */
    public static function activeTextarea($model, $attribute, $options = [])
    {
        $name = isset($options['name']) ? $options['name'] : static::getInputName($model, $attribute);
        if (isset($options['value'])) {
            $value = $options['value'];
            unset($options['value']);
        } else {
            $value = static::getAttributeValue($model, $attribute);
        }
        if (!array_key_exists('id', $options)) {
            $options['id'] = static::getInputId($model, $attribute);
        }
        self::normalizeMaxLength($model, $attribute, $options);
        static::setActivePlaceholder($model, $attribute, $options);
        return static::textarea($name, $value, $options);
    }

    /**
     * 为给定模型属性生成一个单选按钮标签和一个标签。
     * 此方法将根据模型属性值生成 "checked" 的标记属性。
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式。
     * 有关属性表达式的格式请参见 [[getAttributeName()]]。
     * @param array $options 以键值对表示的标记选项。
     * 有关允许属性的详情请参考 [[booleanInput()]]。
     *
     * @return string 生成的单选按钮标记
     */
    public static function activeRadio($model, $attribute, $options = [])
    {
        return static::activeBooleanInput('radio', $model, $attribute, $options);
    }

    /**
     * 为给定的模型属性生成复选框标记和标签。
     * 此方法将根据模型属性值生成 "checked" 的标记属性。
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式。
     * 有关属性表达式的格式请参见 [[getAttributeName()]]。
     * @param array $options 以键值对表示的标记选项。
     * 有关允许属性的详情请参考 [[booleanInput()]]。
     *
     * @return string 生成的复选框标记
     */
    public static function activeCheckbox($model, $attribute, $options = [])
    {
        return static::activeBooleanInput('checkbox', $model, $attribute, $options);
    }

    /**
     * 生成布尔输入
     * 此方法主要由 [[activeCheckbox()]] 和 [[activeRadio()]] 调用。
     * @param string $type 输入类型。这可以是 `radio` 或者 `checkbox`。
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式。
     * 有关属性表达式的格式请参见 [[getAttributeName()]]。
     * @param array $options 以键值对表示的标记选项。
     * 有关允许属性的详情请参考 [[booleanInput()]]。
     * @return string 生成的输入元素
     * @since 2.0.9
     */
    protected static function activeBooleanInput($type, $model, $attribute, $options = [])
    {
        $name = isset($options['name']) ? $options['name'] : static::getInputName($model, $attribute);
        $value = static::getAttributeValue($model, $attribute);

        if (!array_key_exists('value', $options)) {
            $options['value'] = '1';
        }
        if (!array_key_exists('uncheck', $options)) {
            $options['uncheck'] = '0';
        } elseif ($options['uncheck'] === false) {
            unset($options['uncheck']);
        }
        if (!array_key_exists('label', $options)) {
            $options['label'] = static::encode($model->getAttributeLabel(static::getAttributeName($attribute)));
        } elseif ($options['label'] === false) {
            unset($options['label']);
        }

        $checked = "$value" === "{$options['value']}";

        if (!array_key_exists('id', $options)) {
            $options['id'] = static::getInputId($model, $attribute);
        }

        return static::$type($name, $checked, $options);
    }

    /**
     * 为给定的模型属性生成下拉列表。
     * 下拉列表的选择取自模型属性的值。
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式。
     * 有关属性表达式的格式请参见 [[getAttributeName()]]。
     * @param array $items 选项数据项。数组键是选项值，
     * 数组值是相应的选项标签。数组也可以嵌套（即某些数组值也是数组）。
     * 对于每个子阵列，将产生一个选项组，其标签是与子阵列相关联的键。
     * 如果您有数据模型列表，
     * 可以使用 [[\yii\helpers\ArrayHelper::map()]] 将其转换为上述格式。
     *
     * 注意，这些值和标签将通过该方法自动 HTML 编码，
     * 标签中的空白空间也将被 HTML 编码。
     * @param array $options 以键值对表示的标记选项。以下选项是专门处理的：
     *
     * - prompt：字符串，作为第一个选项显示的提示文本。从版本 2.0.11 起，
     *   您可以使用数组覆盖该值并设置其他标记属性：
     *
     *   ```php
     *   ['text' => 'Please select', 'options' => ['value' => 'none', 'class' => 'prompt', 'label' => 'Select']],
     *   ```
     *
     * - options：数组，选择选项标记的属性。数组键必须是有效的选项值，
     *   数组值是对应选项标记的额外属性。例如，
     *
     *   ```php
     *   [
     *       'value1' => ['disabled' => true],
     *       'value2' => ['label' => 'value 2'],
     *   ];
     *   ```
     *
     * - groups：数组，optgroup 标记的属性。它的结构类似于 'options'，
     *   除了数组键表示在 $items 中指定的 optgroup 标签。
     * - encodeSpaces：布尔，是否将选项提示和选项值中的空格编码为字符。
     *   默认是 false。
     * - encode：布尔，是否对选项提示和选项值字符进行编码。
     *   默认是 'ture'。此选项从 2.0.3 开始提供。
     *
     * 其余选项将渲染为结果标记的属性。
     * 这些值将使用 [[encode()]] 进行 HTML 编码。如果这个值不存在，它将不渲染相应的属性。
     * 有关如何渲染属性的详细信息请参考 [[renderTagAttributes()]]。
     *
     * @return string 生成的下拉列表标记
     */
    public static function activeDropDownList($model, $attribute, $items, $options = [])
    {
        if (empty($options['multiple'])) {
            return static::activeListInput('dropDownList', $model, $attribute, $items, $options);
        }

        return static::activeListBox($model, $attribute, $items, $options);
    }

    /**
     * 生成列表框。
     * 列表框的选择取自模型属性的值。
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式。
     * 有关属性表达式的格式请参见 [[getAttributeName()]]。
     * @param array $items 选项数据项。数组键是选项值，
     * 数组值是相应的选项标签。数组也可以嵌套（即某些数组值也是数组）。
     * 对于每个子阵列，将产生一个选项组，其标签是与子阵列相关联的键。
     * 如果您有数据模型列表，
     * 可以使用 [[\yii\helpers\ArrayHelper::map()]] 将其转换为上述格式。
     *
     * 注意，这些值和标签将通过该方法自动 HTML 编码，
     * 标签中的空白空间也将被 HTML 编码。
     * @param array $options 以键值对表示的标记选项。以下选项是专门处理的：
     *
     * - prompt：字符串，作为第一个选项显示的提示文本。从版本 2.0.11 起，
     *   您可以使用数组覆盖该值并设置其他标记属性：
     *
     *   ```php
     *   ['text' => 'Please select', 'options' => ['value' => 'none', 'class' => 'prompt', 'label' => 'Select']],
     *   ```
     *
     * - options：数组，选择选项标记的属性。数组键必须是有效的选项值，
     *   数组值是对应选项标记的额外属性。例如，
     *
     *   ```php
     *   [
     *       'value1' => ['disabled' => true],
     *       'value2' => ['label' => 'value 2'],
     *   ];
     *   ```
     *
     * - groups：数组，optgroup 标记的属性。它的结构类似于 'options'，
     *   除了数组键表示在 $items 中指定的 optgroup 标签。
     * - unselect：字符串，未选择任何选项时将提交的值。
     *   设置此属性后，将生成一个隐藏字段，这样，
     *   如果在多个模式下没有选择任何选项，我们仍然可以获取已发布的取消选择值。
     * - encodeSpaces：布尔，是否将选项提示和选项值中的空格编码为字符。
     *   默认是 false。
     * - encode：布尔，是否对选项提示和选项值字符进行编码。
     *   默认是 'ture'。此选项从 2.0.3 开始提供。
     *
     * 其余选项将渲染为结果标记的属性。
     * 这些值将使用 [[encode()]] 进行 HTML 编码。如果这个值不存在，它将不渲染相应的属性。
     * 有关如何渲染属性的详细信息请参考 [[renderTagAttributes()]]。
     *
     * @return string 生成的列表框标记
     */
    public static function activeListBox($model, $attribute, $items, $options = [])
    {
        return static::activeListInput('listBox', $model, $attribute, $items, $options);
    }

    /**
     * 生成复选框列表。
     * 复选框列表允许多种选择，像 [[listBox()]]。
     * 因此，相应的提交值是一个数组。
     * The selection of the checkbox list is taken from the value of the model attribute.
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式。
     * 有关属性表达式的格式请参见 [[getAttributeName()]]。
     * @param array $items 用于生成复选框的数据项。
     * 数组键是复选框值，数组值是相应的标签。
     * 请注意，标签将不会被 HTML 编码，而值将被编码。
     * @param array $options 复选框列表容器标记的选项（name=>config）。
     *以下选项是专门处理的：
     *
     * - tag：字符串 |false, 容器元素的标记名。False 用于在没有容器的情况下呈现复选框。
     *   也可以参考 [[tag()]]。
     * - unselect：字符串，当没有选中任何复选框时应提交的值。
     *   您可以将此选项设置为 null，以防止默认值提交。
     *   如果未设置此选项，将提交一个空字符串。
     * - encode：布尔型，是否对复选框标签进行 HTML 编码。默认是 true。
     *   如果设置了 `item` 选项，则忽略此选项。
     * - separator：字符串，区分 HTML 代码项。
     * - itemOptions：数组，使用 [[checkbox()]] 生成复选框标记的选项。
     * - item：回调，可用于自定义生成与 $items 中单个项目对应的HTML代码的回调。
     *   此回调的签名必须是：
     *
     *   ```php
     *   function ($index, $label, $name, $checked, $value)
     *   ```
     *
     *   其中 $index 是整个列表中复选框的零基索引； 
     *   $label 是复选框的标签；$name，$value 和 $checked 代表这个名字，
     *   值和复选框输入的选中状态。
     *
     * 有关如何渲染属性的详细信息请参考 [[renderTagAttributes()]]。
     *
     * @return string 生成的复选框列表
     */
    public static function activeCheckboxList($model, $attribute, $items, $options = [])
    {
        return static::activeListInput('checkboxList', $model, $attribute, $items, $options);
    }

    /**
     * 生成单选按钮列表。
     * 单选按钮列表类似于复选框列表，但它只允许进行单个选择。
     * 单选按钮的选择取自模型属性的值。
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式。
     * 有关属性表达式的格式请参见 [[getAttributeName()]]。
     * @param array $items 用于生成单选按钮的数据项。
     * 数组键是单选框的值，数组的值是对应的标签。
     * 请注意，标签将不会被 HTML 编码，而值将被编码。
     * @param array $options 单选按钮列表容器标记的选项（name=>config）。
     *以下选项是专门处理的：
     *
     * - tag：字符串 |false，容器元素的标记名。假以呈现不带容器的单选按钮。
     *   也可以参考 [[tag()]]。
     * - unselect：字符串，未选择任何单选按钮时应提交的值。
     *   您可以将此选项设置为空，以防止提交默认值。
     *   如果未设置此选项，则将提交空字符串。
     * - encode：布尔型，是否 HTML 编码复选框标签。默认是 true。
     *   如果设置了 `item` 选项，则忽略此选项。
     * - separator：字符串，区分 HTML 代码。
     * - itemOptions：数组，使用 [[radio()]] 生成单选按钮标记的选项。
     * - item：回调，可用于自定义生成与 $items 中单个项目对应的 HTML 代码生成的回调。
     *   此回调的签名必须是：
     *
     *   ```php
     *   function ($index, $label, $name, $checked, $value)
     *   ```
     *
     *   其中 $index 是整个列表中单选按钮的零基索引；
     *   $label 是单选按钮的标签；$name，$value 和 $checked 代表这个名字，
     *   值和单选按钮输入的检查状态。
     *
     * 有关如何渲染属性的详细信息请参考 [[renderTagAttributes()]]。
     *
     * @return string 生成的单选按钮列表
     */
    public static function activeRadioList($model, $attribute, $items, $options = [])
    {
        return static::activeListInput('radioList', $model, $attribute, $items, $options);
    }

    /**
     * 生成输入字段列表。
     * 此方法主要由 [[activeListBox()]]，[[activeRadioList()]] 和 [[activeCheckboxList()]] 调用。
     * @param string $type 输入类型。这可以是 'listBox'，'radioList'，或者 'checkBoxList'。
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式。
     * 有关属性表达式的格式请参见 [[getAttributeName()]]。
     * @param array $items 用于生成输入字段的数据项。
     * 数组键是输入框的值，数组的值是对应的标签。
     * 请注意，标签将不会被 HTML 编码，而值将被编码。
     * @param array $options 输入列表的选项（name => config）。
     * 支持的特殊选项取决于 `$type` 指定的输入类型。
     * @return string 生成的输入列表
     */
    protected static function activeListInput($type, $model, $attribute, $items, $options = [])
    {
        $name = isset($options['name']) ? $options['name'] : static::getInputName($model, $attribute);
        $selection = isset($options['value']) ? $options['value'] : static::getAttributeValue($model, $attribute);
        if (!array_key_exists('unselect', $options)) {
            $options['unselect'] = '';
        }
        if (!array_key_exists('id', $options)) {
            $options['id'] = static::getInputId($model, $attribute);
        }

        return static::$type($name, $selection, $items, $options);
    }

    /**
     * 呈现可由 [[dropDownList()]] 和 [[listBox()]] 使用的选项标记。
     * @param string|array|null $selection 选定的值。用于单个选择的字符串或用于多个选择的数组。
     * @param array $items 选项数据项。数组键是选项值，
     * 数组的值是对应的标签。数组也可以嵌套（即某些数组值也是数组）。
     * 对于每个子阵列，将产生一个选项组，其标签是与子阵列相关联的键。
     * 如果您有数据模型列表，
     * 可以使用 [[\yii\helpers\ArrayHelper::map()]] 将其转换为上述格式。
     *
     * 注意，这些值和标签将通过该方法自动 HTML 编码，
     * 标签也将被 HTML 编码。
     * @param array $tagOptions 传递给 [[dropDownList()]] 或 [[listBox()]] 调用的 $options 参数。
     * 这个方法将去掉这些元素，如果有："prompt"，"options" 和 "groups"。
     * 有关这些元素的说明，请参阅 [[dropDownList()]] 中的更多详细信息。
     *
     * @return string 生成的列表选项
     */
    public static function renderSelectOptions($selection, $items, &$tagOptions = [])
    {
        if (ArrayHelper::isTraversable($selection)) {
            $selection = array_map('strval', (array)$selection);
        }

        $lines = [];
        $encodeSpaces = ArrayHelper::remove($tagOptions, 'encodeSpaces', false);
        $encode = ArrayHelper::remove($tagOptions, 'encode', true);
        if (isset($tagOptions['prompt'])) {
            $promptOptions = ['value' => ''];
            if (is_string($tagOptions['prompt'])) {
                $promptText = $tagOptions['prompt'];
            } else {
                $promptText = $tagOptions['prompt']['text'];
                $promptOptions = array_merge($promptOptions, $tagOptions['prompt']['options']);
            }
            $promptText = $encode ? static::encode($promptText) : $promptText;
            if ($encodeSpaces) {
                $promptText = str_replace(' ', '&nbsp;', $promptText);
            }
            $lines[] = static::tag('option', $promptText, $promptOptions);
        }

        $options = isset($tagOptions['options']) ? $tagOptions['options'] : [];
        $groups = isset($tagOptions['groups']) ? $tagOptions['groups'] : [];
        unset($tagOptions['prompt'], $tagOptions['options'], $tagOptions['groups']);
        $options['encodeSpaces'] = ArrayHelper::getValue($options, 'encodeSpaces', $encodeSpaces);
        $options['encode'] = ArrayHelper::getValue($options, 'encode', $encode);

        foreach ($items as $key => $value) {
            if (is_array($value)) {
                $groupAttrs = isset($groups[$key]) ? $groups[$key] : [];
                if (!isset($groupAttrs['label'])) {
                    $groupAttrs['label'] = $key;
                }
                $attrs = ['options' => $options, 'groups' => $groups, 'encodeSpaces' => $encodeSpaces, 'encode' => $encode];
                $content = static::renderSelectOptions($selection, $value, $attrs);
                $lines[] = static::tag('optgroup', "\n" . $content . "\n", $groupAttrs);
            } else {
                $attrs = isset($options[$key]) ? $options[$key] : [];
                $attrs['value'] = (string) $key;
                if (!array_key_exists('selected', $attrs)) {
                    $attrs['selected'] = $selection !== null &&
                        (!ArrayHelper::isTraversable($selection) && !strcmp($key, $selection)
                        || ArrayHelper::isTraversable($selection) && ArrayHelper::isIn((string)$key, $selection));
                }
                $text = $encode ? static::encode($value) : $value;
                if ($encodeSpaces) {
                    $text = str_replace(' ', '&nbsp;', $text);
                }
                $lines[] = static::tag('option', $text, $attrs);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * 呈现 HTML 标记属性。
     *
     * 值为布尔类型的属性将被视为
     * [boolean attributes](http://www.w3.org/TR/html5/infrastructure.html#boolean-attributes)。
     *
     * 值为空的属性将不会渲染。
     *
     * 这些值的属性将使用 [[encode()]] 进行 HTML 编码。
     *
     * "data" 属性在接收数组值时被专门处理。在这种情况下，
     * 数组将被 "expanded" 并且列表数据属性将被渲染。例如，
     * 如果 `'data' => ['id' => 1, 'name' => 'yii']`，则将渲染：
     * `data-id="1" data-name="yii"`。
     * 额外地 `'data' => ['params' => ['id' => 1, 'name' => 'yii']，'status' => 'ok']` 将被渲染成：
     * `data-params='{"id":1,"name":"yii"}' data-status="ok"`。
     *
     * @param array $attributes 要渲染的属性。属性值将使用 [[encode()]] 进行 HTML 编码。
     * @return string 渲染结果。如果属性不是空的，
     * 它们将渲染为一个带有前导空格的字符串（以便它可以直接附加到标记中的标记名称）。
     * 如果没有属性，则返回空字符串。
     * @see addCssClass()
     */
    public static function renderTagAttributes($attributes)
    {
        if (count($attributes) > 1) {
            $sorted = [];
            foreach (static::$attributeOrder as $name) {
                if (isset($attributes[$name])) {
                    $sorted[$name] = $attributes[$name];
                }
            }
            $attributes = array_merge($sorted, $attributes);
        }

        $html = '';
        foreach ($attributes as $name => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $html .= " $name";
                }
            } elseif (is_array($value)) {
                if (in_array($name, static::$dataAttributes)) {
                    foreach ($value as $n => $v) {
                        if (is_array($v)) {
                            $html .= " $name-$n='" . Json::htmlEncode($v) . "'";
                        } else {
                            $html .= " $name-$n=\"" . static::encode($v) . '"';
                        }
                    }
                } elseif ($name === 'class') {
                    if (empty($value)) {
                        continue;
                    }
                    $html .= " $name=\"" . static::encode(implode(' ', $value)) . '"';
                } elseif ($name === 'style') {
                    if (empty($value)) {
                        continue;
                    }
                    $html .= " $name=\"" . static::encode(static::cssStyleFromArray($value)) . '"';
                } else {
                    $html .= " $name='" . Json::htmlEncode($value) . "'";
                }
            } elseif ($value !== null) {
                $html .= " $name=\"" . static::encode($value) . '"';
            }
        }

        return $html;
    }

    /**
     * 添加 CSS 类（或者不同的类）到指定的选项。
     *
     * 如果 CSS 类已经在选项中，则不会再添加它。
     * 如果给定选项的类规范是一个数组，并且一些类使用命名（字符串）键放置在那里，
     * 重写此类键将不起作用。例如：
     *
     * ```php
     * $options = ['class' => ['persistent' => 'initial']];
     * Html::addCssClass($options, ['persistent' => 'override']);
     * var_dump($options['class']); // outputs: array('persistent' => 'initial');
     * ```
     *
     * @param array $options 要修改的选项。
     * @param string|array $class 要添加的 CSS 类
     * @see mergeCssClasses()
     * @see removeCssClass()
     */
    public static function addCssClass(&$options, $class)
    {
        if (isset($options['class'])) {
            if (is_array($options['class'])) {
                $options['class'] = self::mergeCssClasses($options['class'], (array) $class);
            } else {
                $classes = preg_split('/\s+/', $options['class'], -1, PREG_SPLIT_NO_EMPTY);
                $options['class'] = implode(' ', self::mergeCssClasses($classes, (array) $class));
            }
        } else {
            $options['class'] = $class;
        }
    }

    /**
     * 将现有的 CSS 类与新的 CSS 类合并。
     * 此方法为已命名的现有类提供优先级，而不是其他类。
     * @param array $existingClasses 已经存在的 CSS 类。
     * @param array $additionalClasses 要添加的 CSS 类。
     * @return array 合并结果。
     * @see addCssClass()
     */
    private static function mergeCssClasses(array $existingClasses, array $additionalClasses)
    {
        foreach ($additionalClasses as $key => $class) {
            if (is_int($key) && !in_array($class, $existingClasses)) {
                $existingClasses[] = $class;
            } elseif (!isset($existingClasses[$key])) {
                $existingClasses[$key] = $class;
            }
        }

        return array_unique($existingClasses);
    }

    /**
     * 从指定选项中移除 CSS 类。
     * @param array $options 要修改的选项。
     * @param string|array $class CSS 类被移除
     * @see addCssClass()
     */
    public static function removeCssClass(&$options, $class)
    {
        if (isset($options['class'])) {
            if (is_array($options['class'])) {
                $classes = array_diff($options['class'], (array) $class);
                if (empty($classes)) {
                    unset($options['class']);
                } else {
                    $options['class'] = $classes;
                }
            } else {
                $classes = preg_split('/\s+/', $options['class'], -1, PREG_SPLIT_NO_EMPTY);
                $classes = array_diff($classes, (array) $class);
                if (empty($classes)) {
                    unset($options['class']);
                } else {
                    $options['class'] = implode(' ', $classes);
                }
            }
        }
    }

    /**
     * 将指定的 CSS 样式添加到 HTML 选项中。
     *
     * 如果选项已包含 `style` 元素，
     * 则新样式将与现有样式合并。当新样式和旧样式中都存在 CSS 属性时，
     * 如果 `$overwrite` 为 true，则旧的可能会被覆盖。
     *
     * 例如，
     *
     * ```php
     * Html::addCssStyle($options, 'width: 100px; height: 200px');
     * ```
     *
     * @param array $options 要修改的 HTML 选项。
     * @param string|array $style 新样式字符串（例如 `'width: 100px; height: 200px'`）或者
     * 数组（例如 `['width' => '100px', 'height' => '200px']`）。
     * @param bool $overwrite 如果新样式页包含现有的 CSS 属性，
     * 则是否需要重写存在的 CSS 属性
     * @see removeCssStyle()
     * @see cssStyleFromArray()
     * @see cssStyleToArray()
     */
    public static function addCssStyle(&$options, $style, $overwrite = true)
    {
        if (!empty($options['style'])) {
            $oldStyle = is_array($options['style']) ? $options['style'] : static::cssStyleToArray($options['style']);
            $newStyle = is_array($style) ? $style : static::cssStyleToArray($style);
            if (!$overwrite) {
                foreach ($newStyle as $property => $value) {
                    if (isset($oldStyle[$property])) {
                        unset($newStyle[$property]);
                    }
                }
            }
            $style = array_merge($oldStyle, $newStyle);
        }
        $options['style'] = is_array($style) ? static::cssStyleFromArray($style) : $style;
    }

    /**
     * 从 HTML 选项中移除指定的 CSS 样式。
     *
     * 例如，
     *
     * ```php
     * Html::removeCssStyle($options, ['width', 'height']);
     * ```
     *
     * @param array $options 要修改的 HTML 选项。
     * @param string|array $properties 要删除的 CSS 属性。您可以使用字符串
     * 如果要删除单个属性。
     * @see addCssStyle()
     */
    public static function removeCssStyle(&$options, $properties)
    {
        if (!empty($options['style'])) {
            $style = is_array($options['style']) ? $options['style'] : static::cssStyleToArray($options['style']);
            foreach ((array) $properties as $property) {
                unset($style[$property]);
            }
            $options['style'] = static::cssStyleFromArray($style);
        }
    }

    /**
     * 将 CSS 样式数组转换为字符串表示形式。
     *
     * 例如，
     *
     * ```php
     * print_r(Html::cssStyleFromArray(['width' => '100px', 'height' => '200px']));
     * // will display: 'width: 100px; height: 200px;'
     * ```
     *
     * @param array $style CSS 样式数组。数组键是 CSS 属性名，
     * 数组值是对应的 CSS 属性值。
     * @return string CSS 样式字符串。如果 CSS 样式为空，则返回空值。
     */
    public static function cssStyleFromArray(array $style)
    {
        $result = '';
        foreach ($style as $name => $value) {
            $result .= "$name: $value; ";
        }
        // return null if empty to avoid rendering the "style" attribute
        return $result === '' ? null : rtrim($result);
    }

    /**
     * 将 CSS 样式字符串转换为数组表示形式。
     *
     * 数组键是css属性名和数组值，
     * 数组值是对应的css属性值。
     *
     * 例如，
     *
     * ```php
     * print_r(Html::cssStyleToArray('width: 100px; height: 200px;'));
     * // will display: ['width' => '100px', 'height' => '200px']
     * ```
     *
     * @param string $style CSS 样式字符串
     * @return array CSS 样式的数组表示形式
     */
    public static function cssStyleToArray($style)
    {
        $result = [];
        foreach (explode(';', $style) as $property) {
            $property = explode(':', $property);
            if (count($property) > 1) {
                $result[trim($property[0])] = trim($property[1]);
            }
        }

        return $result;
    }

    /**
     * 返回给定属性表达式中的真实属性名。
     *
     * 属性表达式是以数组索引为前缀和 / 或后缀的属性名。
     * 它主要用于表格数据输入和 / 或数组类型的输入。以下是一些例子：
     *
     * - `[0]content` 用于表格数据输入，
     *   表示表格输入中第一个模型的 "content" 属性；
     * - `dates[0]` 表示 "dates" 属性的第一个数组元素；
     * - `[0]dates[0]` 表示表格输入中第一个模型的
     *   "dates" 属性的第一个数组元素。
     *
     * 如果 `$attribute` 既没有前缀也没有后缀，则返回时将不做任何更改。
     * @param string $attribute 属性名或表达式
     * @return string 没有前缀和后缀的属性名。
     * @throws InvalidArgumentException 如果属性名包含非字字符。
     */
    public static function getAttributeName($attribute)
    {
        if (preg_match(static::$attributeRegex, $attribute, $matches)) {
            return $matches[2];
        }

        throw new InvalidArgumentException('Attribute name must contain word characters only.');
    }

    /**
     * 返回指定属性名或表达式的值。
     *
     * 对于类似于 `[0]dates[0]` 的属性表达式，此方法将返回 `$model->dates[0]` 的值。
     * 有关属性表达式的格式请参见 [[getAttributeName()]]。
     *
     * 如果属性值是 [[ActiveRecordInterface]] 的实例或此类实例的数组，
     * AR 实例的主要的值将被替代。
     *
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式
     * @return string|array 对应的属性值
     * @throws InvalidArgumentException 如果属性名包含非字字符。
     */
    public static function getAttributeValue($model, $attribute)
    {
        if (!preg_match(static::$attributeRegex, $attribute, $matches)) {
            throw new InvalidArgumentException('Attribute name must contain word characters only.');
        }
        $attribute = $matches[2];
        $value = $model->$attribute;
        if ($matches[3] !== '') {
            foreach (explode('][', trim($matches[3], '[]')) as $id) {
                if ((is_array($value) || $value instanceof \ArrayAccess) && isset($value[$id])) {
                    $value = $value[$id];
                } else {
                    return null;
                }
            }
        }

        // https://github.com/yiisoft/yii2/issues/1457
        if (is_array($value)) {
            foreach ($value as $i => $v) {
                if ($v instanceof ActiveRecordInterface) {
                    $v = $v->getPrimaryKey(false);
                    $value[$i] = is_array($v) ? json_encode($v) : $v;
                }
            }
        } elseif ($value instanceof ActiveRecordInterface) {
            $value = $value->getPrimaryKey(false);

            return is_array($value) ? json_encode($value) : $value;
        }

        return $value;
    }

    /**
     * 为指定的属性名或表达式生成适当的输入名。
     *
     * 此方法生成一个名称，
     * 该名称可用作输入名称用以收集指定属性的用户输入。
     * 根据模型的 [[Model::formName|form name]] 和给定的属性名称生成名称。例如，
     * 如果 `Post` 模型的表单名称为`Post`, 然后为 `content` 属性生成的输入名称将是 `Post[content]`。
     *
     * 有关属性表达式的说明请参考 [[getAttributeName()]]。
     *
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式
     * @return string 生成的输入名称
     * @throws InvalidArgumentException 如果属性名包含非字字符。
     */
    public static function getInputName($model, $attribute)
    {
        $formName = $model->formName();
        if (!preg_match(static::$attributeRegex, $attribute, $matches)) {
            throw new InvalidArgumentException('Attribute name must contain word characters only.');
        }
        $prefix = $matches[1];
        $attribute = $matches[2];
        $suffix = $matches[3];
        if ($formName === '' && $prefix === '') {
            return $attribute . $suffix;
        } elseif ($formName !== '') {
            return $formName . $prefix . "[$attribute]" . $suffix;
        }

        throw new InvalidArgumentException(get_class($model) . '::formName() cannot be empty for tabular inputs.');
    }

    /**
     * 为指定的属性名或表达式生成适当的输入 ID。
     *
     * 此方法将结果 [[getInputName()]] 转换为有效的输入 ID。
     * 例如，如果 [[getInputName()]] 返回 `Post[content]`，则此方法将返回 `post-content`。
     * @param Model $model 模型对象
     * @param string $attribute 属性名或表达式。有关属性表达式的说明请参考 [[getAttributeName()]]。
     * @return string 生成的输入 ID
     * @throws InvalidArgumentException 如果属性名包含非字字符。
     */
    public static function getInputId($model, $attribute)
    {
        $charset = Yii::$app ? Yii::$app->charset : 'UTF-8';
        $name = mb_strtolower(static::getInputName($model, $attribute), $charset);
        return str_replace(['[]', '][', '[', ']', ' ', '.'], ['', '-', '-', '', '-', '-'], $name);
    }

    /**
     * 转义正则表达式以在 JavaScript 中使用。
     * @param string $regexp 要转义的正则表达式。
     * @return string 转义的结果。
     * @since 2.0.6
     */
    public static function escapeJsRegularExpression($regexp)
    {
        $pattern = preg_replace('/\\\\x\{?([0-9a-fA-F]+)\}?/', '\u$1', $regexp);
        $deliminator = substr($pattern, 0, 1);
        $pos = strrpos($pattern, $deliminator, 1);
        $flag = substr($pattern, $pos + 1);
        if ($deliminator !== '/') {
            $pattern = '/' . str_replace('/', '\\/', substr($pattern, 1, $pos - 1)) . '/';
        } else {
            $pattern = substr($pattern, 0, $pos + 1);
        }
        if (!empty($flag)) {
            $pattern .= preg_replace('/[^igm]/', '', $flag);
        }

        return $pattern;
    }
}