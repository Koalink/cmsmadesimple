<?php
declare(strict_types=1);

/**
 * CMS Made Simple - Smarty Template Variable Inspector
 * 
 * @package CMS
 * @license GPL v2+
 * @author Robert Campbell <calguy1000@hotmail.com>
 * @version 3.0 - PHP 8.2+ Compatible
 */

if (!function_exists('__cms_function_output_var')) {
    
    /**
     * Generate accessor syntax for variables
     * 
     * @param string $ptype Parent type (object|array)
     * @param string|int $key Current key
     * @param int $depth Nesting depth
     * @return string Accessor syntax
     * @throws LogicException
     */
    function __cms_function_output_accessor(string $ptype, string|int $key, int $depth): string
    {
        if ($depth === 0) {
            return '$' . $key;
        }
        
        return match (strtolower($ptype)) {
            'object' => '->' . $key,
            'array' => is_numeric($key) 
                ? "[{$key}]" 
                : (str_contains((string)$key, ' ') ? "['{$key}']" : ".{$key}"),
            default => throw new LogicException("Invalid accessor type: {$ptype}"),
        };
    }

    /**
     * Output variable structure with type information
     * 
     * @param string|int $key Variable key
     * @param mixed $val Variable value
     * @param string|null $ptype Parent type
     * @param int $depth Current nesting depth
     * @param int $maxDepth Maximum allowed depth
     * @return string HTML representation
     */
    function __cms_function_output_var(
        string|int $key, 
        mixed $val, 
        ?string $ptype = null, 
        int $depth = 0, 
        int $maxDepth = 50
    ): string {
        // Prevent infinite recursion
        if ($depth > $maxDepth) {
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $depth);
            $safeKey = htmlspecialchars((string)$key, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return "{$indent}{$safeKey} <em>(max depth reached)</em><br/>";
        }
        
        $type = get_debug_type($val);
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $depth);
        $acc = __cms_function_output_accessor($ptype ?? 'array', $key, $depth);
        $output = [];
        
        if (is_object($val)) {
            $className = htmlspecialchars($val::class, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $output[] = "{$indent}{$acc} <em>(object: {$className})</em> = {";
            
            $objVars = get_object_vars($val);
            if ($objVars !== []) {
                $output[] = '<br/>';
                foreach ($objVars as $oKey => $oVal) {
                    $output[] = __cms_function_output_var($oKey, $oVal, 'object', $depth + 1, $maxDepth);
                }
            }
            $output[] = "{$indent}}<br/>";
            
        } elseif (is_array($val)) {
            $output[] = "{$indent}{$acc} <em>({$type})</em> = [<br/>";
            
            foreach ($val as $aKey => $aVal) {
                $output[] = __cms_function_output_var($aKey, $aVal, 'array', $depth + 1, $maxDepth);
            }
            $output[] = "{$indent}]<br/>";
            
        } elseif (is_callable($val)) {
            $callableType = is_string($val) ? 'function' : (is_array($val) ? 'method' : 'closure');
            $output[] = "{$indent}{$acc} <em>(callable: {$callableType})</em><br/>";
            
        } else {
            $prefix = $depth === 0 ? '$' . $key : '.' . $key;
            $escapedVal = htmlspecialchars(
                match (true) {
                    is_bool($val) => $val ? 'true' : 'false',
                    is_null($val) => 'null',
                    is_resource($val) => 'resource(' . get_resource_type($val) . ')',
                    default => (string)$val,
                },
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
            $output[] = "{$indent}{$prefix} <em>({$type})</em> = {$escapedVal}<br/>";
        }
        
        return implode('', $output);
    }
}

/**
 * Smarty function to display all template variables
 * 
 * @param array{assign?: string, maxdepth?: int|string, style?: string} $params Function parameters
 * @param object $smarty Smarty template object
 * @return string|null HTML output or null if assigning
 */
function smarty_cms_function_get_template_vars(array $params, object $smarty): ?string
{
    $maxDepth = isset($params['maxdepth']) 
        ? max(1, min((int)$params['maxdepth'], 50)) 
        : 10;
    
    $tplVars = $smarty->getTemplateVars();
    
    // Custom styling support
    $defaultStyle = 'background:#f5f5f5;padding:1rem;border:1px solid #ddd;'
                  . 'border-radius:4px;overflow:auto;font-family:monospace;'
                  . 'font-size:0.875rem;line-height:1.5;max-height:600px;';
    $style = $params['style'] ?? $defaultStyle;
    $safeStyle = htmlspecialchars($style, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Build output using array for better performance
    $output = ["<pre style=\"{$safeStyle}\">"];
    
    if ($tplVars === []) {
        $output[] = '<em>No template variables found.</em>';
    } else {
        foreach ($tplVars as $key => $value) {
            $output[] = __cms_function_output_var($key, $value, null, 0, $maxDepth);
        }
    }
    
    $output[] = '</pre>';
    $result = implode('', $output);
    
    // Assign to variable if requested
    if (isset($params['assign']) && $params['assign'] !== '') {
        $smarty->assign(trim($params['assign']), $result);
        return null;
    }
    
    return $result;
}

/**
 * Display plugin information
 * 
 * @return void
 */
function smarty_cms_about_function_get_template_vars(): void
{
    ?>
        <p><strong>Authors:</strong><p>
		<ul>
			<li>Robert CAMPBELL &lt;calguy1000@hotmail.com&gt;</li>
			<li>Jocelyn LUSSEAU • Koalink &lt;koalink.fr&gt;</li>
		</ul>
        <p><strong>Version:</strong> 3.0</p>
        
        <h4>Usage:</h4>
        <pre style="background: #f5f5f5; padding: 0.5rem; border-radius: 4px;">{get_template_vars}
{get_template_vars assign="debug_info"}
{get_template_vars maxdepth=5}
{get_template_vars style="background:#fff;padding:10px;"}</pre>
        
        <h4>Parameters:</h4>
        <ul style="line-height: 1.6;">
            <li><code>assign</code> - (optional) Variable name to assign result to</li>
            <li><code>maxdepth</code> - (optional) Maximum recursion depth (1-50, default: 50)</li>
            <li><code>style</code> - (optional) Custom CSS style for the output container</li>
        </ul>
        
        <h4>Change History:</h4>
        <ul style="line-height: 1.6;">
            <li><strong>v3.0</strong> - PHP 8.2+ compatibility, strict types, match expressions, named arguments</li>
            <li><strong>v2.0</strong> - Security fixes (XSS prevention), performance improvements</li>
            <li><strong>v1.0</strong> - Initial release</li>
        </ul>
    <?php
}
?>
