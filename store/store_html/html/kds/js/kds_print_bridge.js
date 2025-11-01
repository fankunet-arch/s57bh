/**
 * Toptea KDS - kds_print_bridge.js
 * 核心打印模块 (v1.4 - APK 专用版修复)
 *
 * 核心逻辑:
 * 1. 强制使用 window.AndroidBridge 进行打印，这是为套壳APK设计的唯一路径。
 * 2. 如果 window.AndroidBridge 或其 printRaw 方法未找到，则不再回退到 window.print()，
 * 而是调用 showKdsAlert() 弹出明确的错误提示，避免页面跳转。
 *
 * Engineer: Gemini | Date: 2025-10-31
 */

var KDS_PRINT_BRIDGE = (function () {

    // 辅助函数：替换 {variable_name} 占位符
    function replacePlaceholders(text, data) {
        if (!text || typeof text !== 'string') return '';
        return text.replace(/\{([\w_]+)\}/g, function (match, key) {
            var value = data[key];
            return (value !== undefined && value !== null) ? String(value) : match;
        });
    }

    /**
     * ===================================================================
     * 转换为 TSPL (用于 安卓APK / window.AndroidBridge)
     * ===================================================================
     */
    function convertToTSPL(templateContent, data, templateSize) {
        var tsplCommands = [];
        var yPos = 20; // Y轴起始位置 (点)
        var xStart = 20; // X轴起始位置 (点)

        // 1. 设置标签尺寸 (假设 1mm = 8 dots)
        var width, height;
        if (templateSize === '80mm') {
            width = 80;
            height = 60; // 假设80mm连续纸，本次打印高度60mm (需要动态计算)
        } else {
            var dimensions = templateSize.split('x');
            width = parseInt(dimensions[0] || '50', 10);
            height = parseInt(dimensions[1] || '30', 10);
        }
        
        tsplCommands.push('SIZE ' + width + ' mm, ' + height + ' mm');
        tsplCommands.push('GAP 3 mm, 0 mm');
        tsplCommands.push('CLS'); // 清除缓冲区

        // 2. 遍历命令
        templateContent.forEach(function (command) {
            var type = command.type;
            var value = replacePlaceholders(command.value || '', data);
            var align = command.align || 'left';
            var size = command.size || 'normal';
            var key = replacePlaceholders(command.key || '', data);
            
            var font = 'TSS24.BF2'; // 默认字体 (24x24)
            var multiplier = (size === 'double') ? 2 : 1;
            var lineHeight = (size === 'double') ? 48 : 24;
            
            var text = '';
            
            switch (type) {
                case 'text':
                    text = value;
                    break;
                case 'kv':
                    text = key + ": " + value;
                    break;
                case 'divider':
                    tsplCommands.push('BAR ' + xStart + ',' + yPos + ', ' + (width * 8 - 40) + ', 2');
                    yPos += 10;
                    return;
                case 'feed':
                    yPos += (command.lines || 1) * 20;
                    return;
                case 'cut':
                    return; 
                default:
                    text = "[? " + type + "]";
            }
            
            tsplCommands.push('TEXT ' + xStart + ',' + yPos + ',"' + font + '",0,' + multiplier + ',' + multiplier + ',"' + text + '"');
            yPos += lineHeight + 4;
        });

        // 3. 结束命令
        tsplCommands.push('PRINT 1,1');
        if (templateContent.some(c => c.type === 'cut')) {
             tsplCommands.push('CUT');
        }

        return tsplCommands.join('\r\n');
    }

    /**
     * ===================================================================
     * 公开方法：执行打印 (APK专用逻辑)
     * ===================================================================
     */
    function executePrint(template, data) {
        if (!template || !template.content || !Array.isArray(template.content)) {
            console.error("KDS Print Error: 模板 (template) 无效。");
            showKdsAlert("打印失败：模板格式不正确。", true);
            return;
        }
        if (!data) {
            console.error("KDS Print Error: 数据 (data) 未定义。");
            showKdsAlert("打印失败：无打印数据。", true);
            return;
        }

        var templateSize = template.size || "80mm";
        
        console.log("--- KDS 打印任务 ---");
        console.log("Template:", template);
        console.log("Data:", data);

        // **【核心修复】**
        // 强制检查并使用 AndroidBridge，如果不存在则通过自定义弹窗报错。
        if (window.AndroidBridge && typeof window.AndroidBridge.printRaw === 'function') {
            console.log("检测到 AndroidBridge，使用 TSPL 打印...");
            try {
                var tsplString = convertToTSPL(template.content, data, templateSize);
                console.log("TSPL:\n", tsplString);
                window.AndroidBridge.printRaw(tsplString);
            } catch (e) {
                console.error("TSPL 打印失败:", e);
                showKdsAlert("平板打印失败: " + e.message, true);
            }
        } else {
            // **不再回退到 window.print()**
            console.error("打印失败: 未在 window 对象上检测到 AndroidBridge.printRaw 方法。");
            showKdsAlert("打印失败：未找到安卓打印接口(AndroidBridge)。请确认您正在App中使用，并且App版本是最新的。", true);
        }
    }

    return {
        executePrint: executePrint
    };

})();