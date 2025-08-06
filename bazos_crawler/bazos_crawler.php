<?php
/**
 * Bazos Crawler - PHP Version
 * Web scraper for Bazos.sk classified ads
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('Europe/Bratislava');

// Configuration
$SEARCHES = [
    // Macbook Pro M4 - PC kategória
    [
        "query" => "Macbook Pro M4",
        "cena_od" => 1500,
        "cena_do" => 4000,
        "max_age" => 14,
        "category_id" => 801
    ],
    
    // Yaesu vysielačky - Elektronika kategória  
    [
        "query" => "Yaesu",
        "cena_od" => 30,
        "cena_do" => 2000,
        "max_age" => 14,
        "category_id" => 807
    ]
];

$CATEGORIES = [
    807 => "elektro",     // Elektronika
    1 => "auto",          // Autá
    801 => "pc",          // Počítače
    813 => "mobil",       // Telefóny
    82 => "reality",      // Reality
    85 => "zahrada"       // Dom a záhrada
];

$HEADERS = ["User-Agent: Mozilla/5.0 BazosCrawler/1.0"];
$PUSH_USER = getenv("PUSHOVER_USER");
$PUSH_TOKEN = getenv("PUSHOVER_TOKEN");
$PUSH_URL = "https://api.pushover.net/1/messages.json";

$OUT_DIR = "../web/data/found_items";
$HIST_DIR = "../web/data/history";
$EXPORT = [];

/**
 * Setup logging
 */
function setupLogging() {
    $today_str = date('Y-m-d');
    $log_dir = "../web/data/logs";
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . "/bazos_crawl_{$today_str}.log";
    
    return function($level, $message) use ($log_file) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "{$timestamp} - {$level} - {$message}" . PHP_EOL;
        
        // Write to file
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // Output to console for INFO and above
        if ($level !== 'DEBUG') {
            echo $log_entry;
        }
    };
}

$logger = setupLogging();

/**
 * Generate MD5 hash
 */
function md5_hash($text) {
    return md5($text);
}

/**
 * Parse Bazos date format
 */
function bazos_date($txt) {
    $today = new DateTime();
    
    if (strpos($txt, 'Dnes') !== false) {
        return $today;
    }
    
    if (strpos($txt, 'Včera') !== false) {
        return $today->modify('-1 day');
    }
    
    if (preg_match('/(\d{1,2})\.\s*(\d{1,2})\./', $txt, $matches)) {
        $day = (int)$matches[1];
        $month = (int)$matches[2];
        $year = $today->format('Y');
        
        $date = new DateTime("{$year}-{$month}-{$day}");
        
        if ($date > $today) {
            $date->modify('-1 year');
        }
        
        return $date;
    }
    
    return $today->modify('-9999 days');
}

/**
 * Send Pushover notification
 */
function pushover($title, $msg, $url) {
    global $PUSH_USER, $PUSH_TOKEN, $PUSH_URL, $HEADERS, $logger;
    
    if (!$PUSH_USER || !$PUSH_TOKEN) {
        return;
    }
    
    $logger('DEBUG', "Sending Pushover notification: {$title}");
    
    $data = [
        'token' => $PUSH_TOKEN,
        'user' => $PUSH_USER,
        'title' => substr($title, 0, 250),
        'message' => substr($msg, 0, 1024),
        'url' => $url,
        'url_title' => 'Otvoriť inzerát'
    ];
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $HEADERS) . "\r\nContent-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ]);
    
    $result = file_get_contents($PUSH_URL, false, $context);
    
    if ($result === false) {
        $logger('ERROR', 'Failed to send Pushover notification');
    }
}

/**
 * HTML template for product pages
 */
function getHtmlTemplate($title, $date, $price, $url, $imgs_html, $desc, $contact) {
    return "<!DOCTYPE html>
<html lang='sk'>
<head>
    <meta charset='utf-8'>
    <title>{$title}</title>
    <style>
        :root {
            color-scheme: dark;
            background: #121212;
            color: #e0e0e0;
            font-family: sans-serif;
        }
        a { color: #80cbc4; }
        img { max-width: 100%; height: auto; margin: 10px 0; }
        .meta { font-size: .9em; color: #aaa; }
        .content { white-space: pre-wrap; margin-top: 1em; }
    </style>
</head>
<body>
    <h1>{$title}</h1>
    <p class='meta'>{$date} | {$price}</p>
    <p><a href='{$url}' target='_blank'>Otvoriť inzerát</a></p>
    {$imgs_html}
    <div class='content'>{$desc}</div>
    {$contact}
</body>
</html>";
}

/**
 * Get product details from URL
 */
function detail($url, $folder) {
    global $HEADERS, $logger;
    
    $logger('DEBUG', "Fetching product details from: {$url}");
    
    try {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $HEADERS) . "\r\n",
                'timeout' => 20
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception("Failed to fetch URL");
        }
        
        // Create DOM document
        $dom = new DOMDocument();
        @$dom->loadHTML($response, LIBXML_NOERROR);
        $xpath = new DOMXPath($dom);
        
        // Check if item is still available
        $unavailable_indicators = [
            'inzerát bol vymazaný',
            'inzerát už nie je dostupný',
            'inzerát bol stiahnutý',
            'inzerát neexistuje'
        ];
        
        $page_text = strtolower($dom->textContent);
        $is_available = true;
        foreach ($unavailable_indicators as $indicator) {
            if (strpos($page_text, $indicator) !== false) {
                $is_available = false;
                break;
            }
        }
        
        // Extract description
        $desc_elem = $xpath->query("//div[@class='popisdetail']")->item(0);
        $desc = $desc_elem ? trim($desc_elem->textContent) : "";
        
        // Contact info is usually not on detail page
        $contact = "";
        
        $imgs = [];
        
        // Find images
        $image_elements = $xpath->query("//img[contains(@src, '.jpg')]");
        
        foreach ($image_elements as $i => $img) {
            $src = $img->getAttribute('src');
            if (!$src) continue;
            
            // Filter to get only product images
            if (strpos($src, 'bazos.sk/img/') === false || strpos($src, 't.jpg') !== false) {
                continue;
            }
            
            $img_url = $src;
            if (strpos($img_url, 'http') !== 0) {
                $img_url = 'https://bazos.sk' . $img_url;
            }
            
            $ext = pathinfo($src, PATHINFO_EXTENSION);
            if (!$ext) $ext = 'jpg';
            $ext = '.' . $ext;
            
            // Save to found_items folder
            $filename = sprintf("%02d%s", $i + 1, $ext);
            $filepath = $folder . '/' . $filename;
            
            try {
                $logger('DEBUG', "Downloading image " . ($i + 1) . ": {$img_url}");
                
                $img_context = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'header' => implode("\r\n", $HEADERS) . "\r\n",
                        'timeout' => 20
                    ]
                ]);
                
                $img_content = file_get_contents($img_url, false, $img_context);
                
                if ($img_content !== false) {
                    file_put_contents($filepath, $img_content);
                    $imgs[] = $filepath;
                }
                
            } catch (Exception $e) {
                $logger('WARNING', "Failed to download image {$img_url}: " . $e->getMessage());
            }
        }
        
        $logger('INFO', "Downloaded " . count($imgs) . " images for product");
        return [$desc, $imgs, $contact, $is_available];
        
    } catch (Exception $e) {
        $logger('ERROR', "Failed to fetch product details from {$url}: " . $e->getMessage());
        return ["", [], "", false];
    }
}

/**
 * Main crawl function
 */
function crawl() {
    global $SEARCHES, $CATEGORIES, $HEADERS, $OUT_DIR, $HIST_DIR, $EXPORT, $logger;
    
    $logger('INFO', "Starting Bazos crawler");
    
    // Load existing data to update
    $existing_data = [];
    $index_file = $OUT_DIR . "/index.json";
    
    if (file_exists($index_file)) {
        try {
            $existing_data = json_decode(file_get_contents($index_file), true);
            $logger('INFO', "Loaded " . count($existing_data['ads'] ?? []) . " existing items for updates");
        } catch (Exception $e) {
            $logger('ERROR', "Failed to load existing data: " . $e->getMessage());
        }
    }
    
    foreach ($SEARCHES as $s) {
        $q = $s['query'];
        $o = $s['cena_od'];
        $d = $s['cena_do'];
        $smax = $s['max_age'];
        $cat = $s['category_id'];
        $sub = $CATEGORIES[$cat];
        
        $url = "https://{$sub}.bazos.sk/?hledat=" . urlencode($q) . 
               "&rubriky=www&hlokalita=&humkreis=25&cenaod={$o}&cenado={$d}" .
               "&submit=H%C4%8Aada%C5%A5&order=nejnovejsi";
        
        $logger('INFO', "Searching for '{$q}' in category '{$sub}' with price range {$o}-{$d}€");
        $logger('DEBUG', "Search URL: {$url}");
        
        $hist_file = $HIST_DIR . '/' . md5_hash($url);
        $hist = [];
        
        if (file_exists($hist_file)) {
            $hist = json_decode(file_get_contents($hist_file), true) ?: [];
        }
        
        try {
            $logger('DEBUG', "Fetching search results from: {$url}");
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $HEADERS) . "\r\n",
                    'timeout' => 20
                ]
            ]);
            
            $response = file_get_contents($url, false, $context);
            
            if ($response === false) {
                throw new Exception("Failed to fetch search results");
            }
            
        } catch (Exception $e) {
            $logger('ERROR', "Failed to fetch search results: " . $e->getMessage());
            continue;
        }
        
        $cutoff = new DateTime();
        $cutoff->modify("-{$smax} days");
        $logger('INFO', "Looking for ads newer than " . $cutoff->format('Y-m-d'));
        
        $found_count = 0;
        $processed_count = 0;
        $updated_count = 0;
        
        // Parse HTML
        $dom = new DOMDocument();
        @$dom->loadHTML($response, LIBXML_NOERROR);
        $xpath = new DOMXPath($dom);
        
        // Find all ad boxes
        $boxes = $xpath->query("//div[contains(@class, 'inzeraty') and contains(@class, 'inzeratyflex')]");
        
        foreach ($boxes as $box) {
            $found_count++;
            
            // Find the title link
            $title_elem = $xpath->query(".//div[@class='inzeratynadpis']//h2[@class='nadpis']//a", $box)->item(0);
            if (!$title_elem) continue;
            
            $link = $title_elem->getAttribute('href');
            if (strpos($link, '/') === 0) {
                $link = "https://bazos.sk" . $link;
            }
            
            $title = trim($title_elem->textContent);
            
            // Find price
            $price_elem = $xpath->query(".//div[@class='inzeratycena']", $box)->item(0);
            $price = $price_elem ? trim($price_elem->textContent) : "";
            
            // Find date
            $date_elem = $xpath->query(".//div[@class='inzeratynadpis']//span[@class='velikost10']", $box)->item(0);
            $date_text = $date_elem ? trim($date_elem->textContent) : "";
            $date = bazos_date($date_text);
            
            // Extract location info
            $location_elem = $xpath->query(".//div[@class='inzeratylok']", $box)->item(0);
            $location = $location_elem ? trim($location_elem->textContent) : "";
            
            // Extract view count
            $view_elem = $xpath->query(".//div[@class='inzeratyview']", $box)->item(0);
            $view_count = $view_elem ? trim($view_elem->textContent) : "";
            
            // Extract seller info
            $seller_info = "";
            
            $ad_id = md5_hash($link);
            
            // Check if this is an existing item that needs updating
            $existing_item = null;
            foreach ($existing_data['ads'] ?? [] as $item) {
                if ($item['url'] === $link) {
                    $existing_item = $item;
                    break;
                }
            }
            
            if ($existing_item) {
                // Update existing item with new information
                $logger('INFO', "Updating existing item: '{$title}'");
                
                // Get updated details
                list($desc, $imgs, $contact, $is_available) = detail($link, dirname($existing_item['htmlPath'] ?? ''));
                
                // Update the existing item
                $existing_item['price'] = $price;
                $existing_item['view_count'] = $view_count;
                $existing_item['location'] = $location;
                $existing_item['description'] = $desc;
                $existing_item['contact'] = $contact;
                $existing_item['is_available'] = $is_available;
                $existing_item['last_updated'] = date('c');
                
                // Update images if new ones are available
                if ($imgs) {
                    $existing_item['images'] = array_map(function($img) use ($OUT_DIR) {
                        return 'data/found_items/' . str_replace($OUT_DIR . '/', '', $img);
                    }, $imgs);
                }
                
                $updated_count++;
                continue;
            }
            
            if ($date < $cutoff) {
                $logger('DEBUG', "Skipping old ad '{$title}' from " . $date->format('Y-m-d'));
                continue;
            }
            
            if (in_array($link, $hist)) {
                $logger('DEBUG', "Skipping already processed ad: {$link}");
                continue;
            }
            
            $logger('INFO', "Processing new ad: '{$title}' - {$price} (" . $date->format('Y-m-d') . ")");
            $logger('INFO', "Product URL: {$link}");
            
            // Create directories
            $day_dir = $OUT_DIR . '/' . $q . '/' . $date->format('Y-m-d');
            if (!is_dir($day_dir)) {
                mkdir($day_dir, 0755, true);
            }
            
            // Get product details with images
            list($desc, $imgs, $contact, $is_available) = detail($link, $day_dir);
            
            $imgs_html = "";
            foreach ($imgs as $img) {
                $imgs_html .= '<img src="' . basename($img) . '">';
            }
            
            // Create HTML file
            $html_content = getHtmlTemplate($title, $date->format('Y-m-d'), $price, $link, $imgs_html, $desc, $contact);
            file_put_contents($day_dir . '/' . $ad_id . '.html', $html_content);
            
            // Send notification
            pushover($title, $price, $link);
            
            // Add to export with relative paths
            $product_data = [
                'id' => $ad_id,
                'title' => $title,
                'price' => $price,
                'date' => $date->format('Y-m-d'),
                'found_at' => date('c'),
                'query' => $q,
                'url' => $link,
                'images' => array_map(function($img) use ($OUT_DIR) {
                    return 'data/found_items/' . str_replace($OUT_DIR . '/', '', $img);
                }, $imgs),
                'htmlPath' => 'data/found_items/' . str_replace($OUT_DIR . '/', '', $day_dir . '/' . $ad_id . '.html'),
                'description' => $desc,
                'contact' => $contact,
                'location' => $location,
                'view_count' => $view_count,
                'seller_info' => $seller_info,
                'category' => $sub,
                'is_available' => $is_available
            ];
            
            $EXPORT[] = $product_data;
            $hist[] = $link;
            $processed_count++;
            
            $logger('INFO', "Successfully processed product '{$title}' with " . count($imgs) . " images");
        }
        
        $logger('INFO', "Found {$found_count} ads, processed {$processed_count} new ads, updated {$updated_count} existing ads for query '{$q}'");
        file_put_contents($hist_file, json_encode($hist));
    }
    
    // Combine existing and new data
    $all_ads = array_merge($existing_data['ads'] ?? [], $EXPORT);
    
    $logger('INFO', "Crawler finished. Total products: " . count($all_ads));
    file_put_contents($index_file, json_encode(['ads' => $all_ads], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Main execution
if (!is_dir($OUT_DIR)) {
    mkdir($OUT_DIR, 0755, true);
}

if (!is_dir($HIST_DIR)) {
    mkdir($HIST_DIR, 0755, true);
}

crawl();
?> 