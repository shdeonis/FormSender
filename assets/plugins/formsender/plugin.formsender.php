<?php

use Illuminate\Support\Facades\Route;
use Symfony\Component\Finder\Finder;

if(!function_exists('app')) return;
if(evo()->event->name == 'OnLoadSettings') {
    $url = $url ?? '/forms';
    Route::post($url, function(){
        $formid = request()->input('formid');
        if (!request()->ajax() || empty($formid) || !is_scalar($formid)) evo()->sendErrorPage();
        $files = [];
        try {
            foreach (Finder::create()->files()->name('*.php')->in(EVO_CORE_PATH . 'custom/forms/') as $file) {
                $files[basename($file->getRealPath(), '.php')] = $file->getRealPath();
            }
        } catch (Exception $e) {
        };
        if (isset($files[$formid])) {
            evo()->invokeEvent('OnWebPageInit');
            $params = require($files[$formid]);
            $snippet = $params['snippet'] ?? 'FormLister';
            unset($params['snippet']);
            $params['api'] = $params['api'] ?? 2;
            $params['apiFormat'] = 'array';

            foreach (['prepare', 'prepareProcess', 'prepareAfterProcess'] as $param) {
                if (empty($params[$param])) {
                    $params[$param] = [];
                } else if (!is_array($params[$param])) {
                    $params[$param] = [$params[$param]];
                }
            }

            $params = array_merge($params, [
                'prepareProcess' => array_merge([
                    //'prepareAddPageToLetter',
                    //'prepareAddUTMLabelsToLetter',
                    function($data, $FormLister) {
                        if (isset($data['pid']) && is_numeric($data['pid'])) {
                            $FormLister->setField('page', evo()->makeUrl($data['pid'], '', '', 'full'));
                        }
                        
                        if (!function_exists('parseQueryParams')) {
                            function parseQueryParams($query) {
                                $utmparams = [
                                    'utm_source'   => 'Рекламная система',
									'utm_medium'   => 'Тип трафика',
                                    'utm_campaign' => 'Кампания',
                                    'utm_content'  => 'Содержание объявления',
                                    'utm_term'     => 'Ключевое слово',
                                    'keyword'      => 'Ключевое слово',
                                    'q'            => 'Поисковая фраза',
                                    'query'        => 'Поисковая фраза',
                                    'text'         => 'Поисковая фраза',
                                    'words'        => 'Поисковая фраза',
                                ];
                                $crawlers = ['yandex.ru', 'rambler.ru', 'google.ru', 'google.com', 'mail.ru', 'bing.com', 'qip.ru'];
                                $out = $params = [];
                                if (preg_match('/\?(.+)$/', urldecode($query), $parts)) {
                                    foreach ($crawlers as $crawler) {
                                        if (stristr($parts[1], $crawler)) {
                                            $out['Система'] = $crawler;
                                        }
                                    }
                        
                                    parse_str($parts[1], $params);
                        
                                    foreach ($utmparams as $name => $title) {
                                        if (!empty($params[$name])) {
                                            $out[$title] = (md5($params[$name]) == md5(iconv('UTF-8', 'UTF-8', $params[$name])) ? $params[$name] : iconv('cp1251', 'utf-8', $params[$name]));
                                        }
                                    }
                        
                                    if (!empty($out)) {
                                        return $out;
                                    }
                                }
                        
                                return null;
                            }
                        }
                        
                        $utm = '';
                        
                        foreach (['sreferer' => 'Параметры перехода', 'squery' => 'Параметры визита'] as $section => $sectionname) {
                            if (isset($_POST[$section]) && is_string($_POST[$section])) {
                                $params = parseQueryParams($_POST[$section]);
                                if (!empty($params)) {
                                    $out = '';
                                    foreach ($params as $key => $value) {
                                        $out .= '<tr><td>' . $key . ':&nbsp;</td><td>' . htmlspecialchars($value) . '</td></tr>';
                                    }
                        
                                    $utm .= '<br><b>' . $sectionname . ':</b>' . '<table><tbody>' . $out . '</tbody></table>';
                                }
                            }
                        }
                        
                        $FormLister->setPlaceholder('utm', $utm);
                    }
                ],$params['prepareProcess'])
            ]);
            
            return evo()->runSnippet($snippet, $params);
        } else {
            evo()->sendErrorPage();
        }
    });
}
if(evo()->event->name == 'OnLoadWebDocument') {
    evo()->regClientStartupHTMLBlock('<script defer src="' . MODX_SITE_URL . 'assets/plugins/formsender/formsender.min.js"></script>');
} 
