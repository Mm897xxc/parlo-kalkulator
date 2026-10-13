<?php
/**
* Template Name: Single Store Page
* Description: Custom template.
*
* Sadržaj stranice (kuponi, H1/H2, FAQ) i dalje se uređuje u adminu.
* Template oko tog sadržaja automatski dodaje SEO sekcije iz config-countries.php ($EKP_TEXTS):
*  - oznaku provjere + datum zadnje provjere i ime urednika
*  - tablicu svih kupona (čita se iz .coupon-box blokova na stranici)
*  - "Vrijedi do" i brojač korištenja na svakom kuponu
*  - uputu korak po korak + savjete o vremenu aktivacije
*  - sekciju za postojeće (stare) korisnike
*  - kategorijske popuste, savjet urednika, interne linkove
*  - dodatna FAQ pitanja + JSON-LD (FAQPage, HowTo, WebPage)
*/

global $COUNTRIES, $EKP_TEXTS, $EKP_SETTINGS;

if (have_posts()) {
    the_post();
}
$ekp_post_id = get_the_ID() ?: get_queried_object_id();

$c_lang  = get_post_meta($ekp_post_id, 'page_language_code', true) ?: 'en';
$c_title = get_post_meta($ekp_post_id, 'custom_meta_title', true) ?: get_the_title($ekp_post_id);
$c_desc  = get_post_meta($ekp_post_id, 'custom_meta_description', true);

$current = $COUNTRIES[$c_lang] ?? $COUNTRIES['es-MX'];

$labels = $current['labels'];
$paths  = $current['paths'];

$ekp_settings  = (isset($EKP_SETTINGS) && is_array($EKP_SETTINGS)) ? $EKP_SETTINGS : [];
$ekp_all_texts = (isset($EKP_TEXTS) && is_array($EKP_TEXTS)) ? $EKP_TEXTS : [];
$t = $ekp_all_texts[$current['lang_pack'] ?? 'en'] ?? ($ekp_all_texts['en'] ?? null);
$ekp_enabled = is_array($t);

/* hreflang (stranice koje postoje i međusobno se referenciraju) */
$ekp_hreflang = [
    'es-AR' => 'https://ekuponi.com/cupones-y-codigos-descuento-temu-argentina/',
    'es-CO' => 'https://ekuponi.com/cupones-y-codigos-descuento-temu-colombia/',
    'cs-CZ' => 'https://ekuponi.com/temu-kupony-a-slevove-kody/',
    'es-DO' => 'https://ekuponi.com/cupones-y-codigos-descuento-temu-republica-dominicana/',
    'es-SV' => 'https://ekuponi.com/cupones-y-codigos-descuento-temu-el-salvador/',
    'fr-FR' => 'https://ekuponi.com/code-promo-temu/',
    'de-DE' => 'https://ekuponi.com/temu-gutscheincode/',
    'el-GR' => 'https://ekuponi.com/%ce%ba%cf%89%ce%b4%ce%b9%ce%ba%ce%bf%ce%af-%ce%ad%ce%ba%cf%80%cf%84%cf%89%cf%83%ce%b7%cf%82-%ce%ba%ce%bf%cf%85%cf%80%cf%8c%ce%bd%ce%b9%ce%b1-temu/',
    'hu-HU' => 'https://ekuponi.com/temu-kuponok-es-akciok/',
    'it-IT' => 'https://ekuponi.com/buoni-sconto-e-codici-sconto-temu/',
    'es-MX' => 'https://ekuponi.com/cupones-y-codigos-descuento-temu-mexico/',
    'nl-NL' => 'https://ekuponi.com/temu-kortingscodes-coupons/',
    'es-PE' => 'https://ekuponi.com/cupones-y-codigos-descuento-temu-peru/',
    'pl-PL' => 'https://ekuponi.com/temu-kupony-i-kody-rabatowe/',
    'pt-PT' => 'https://ekuponi.com/codigos-de-desconto-cupoes-temu/',
    'ro-RO' => 'https://ekuponi.com/temu-reducere-si-cupoane/',
    'es-ES' => 'https://ekuponi.com/cupones-y-codigos-descuento-temu/',
    'sv-SE' => 'https://ekuponi.com/temu-rabattkoder-kuponger/',
    'uk-UA' => 'https://ekuponi.com/temu-%d0%ba%d1%83%d0%bf%d0%be%d0%bd%d0%b8-%d1%82%d0%b0-%d0%bf%d1%80%d0%be%d0%bc%d0%be%d0%ba%d0%be%d0%b4%d0%b8-%d0%b4%d0%be-70/',
    'en-GB' => 'https://ekuponi.com/temu-coupon-codes-uk/',
    'en-US' => 'https://ekuponi.com/temu-coupon-codes-us/',
    'x-default' => 'https://ekuponi.com/temu-coupon-codes-us/',
];

if (!function_exists('ekp_tr')) {
    function ekp_tr($text, $vars = []) {
        $map = [];
        foreach ($vars as $k => $v) {
            $map['{' . $k . '}'] = (string) $v;
        }
        return strtr((string) $text, $map);
    }
}

if (!function_exists('ekp_process_content')) {
    /**
     * Čita kupone i FAQ iz HTML-a stranice i postavlja markere (HTML komentare)
     * na mjesta gdje template ubacuje nove sekcije. Ne mijenja postojeći sadržaj.
     */
    function ekp_process_content($html) {
        $out = ['html' => $html, 'coupons' => [], 'faq' => [], 'has_faq' => false, 'has_top' => false];
        if (!class_exists('DOMDocument') || trim($html) === '') {
            return $out;
        }

        $dom  = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $ok   = $dom->loadHTML('<?xml encoding="UTF-8"?><div id="ekp-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            return $out;
        }
        $dom->encoding = 'UTF-8';

        $xp   = new DOMXPath($dom);
        $root = $xp->query('//div[@id="ekp-root"]')->item(0);
        if (!$root) {
            return $out;
        }
        $cls = function ($c) {
            return "contains(concat(' ', normalize-space(@class), ' '), ' " . $c . " ')";
        };
        $clean = function ($s) {
            return trim(preg_replace('/\s+/u', ' ', $s));
        };

        $i = 0;
        foreach ($xp->query('.//div[' . $cls('coupon-box') . ']', $root) as $box) {
            $h2        = $xp->query('.//h2', $box)->item(0);
            $code_node = $xp->query('.//*[' . $cls('code-box') . ']//span', $box)->item(0);
            $code      = $code_node ? $clean($code_node->textContent) : '';

            $desc = '';
            foreach ($xp->query('.//p', $box) as $p) {
                $pc = ' ' . $p->getAttribute('class') . ' ';
                if (strpos($pc, ' copied-message ') !== false || strpos($pc, ' shop-btn ') !== false) {
                    continue;
                }
                $style = str_replace(' ', '', strtolower($p->getAttribute('style')));
                $txt   = $clean($p->textContent);
                if ($txt === '' || strpos($style, 'font-size:11px') !== false) {
                    continue;
                }
                $desc = $txt;
                break;
            }

            $a = $xp->query('.//a[@href]', $box)->item(0) ?: $xp->query('ancestor::a[@href]', $box)->item(0);

            $key = $code !== '' ? $code : 'deal-' . ($i + 1);
            $box->setAttribute('data-ekp-key', $key);

            $out['coupons'][] = [
                'key'   => $key,
                'title' => $h2 ? $clean($h2->textContent) : '',
                'desc'  => $desc,
                'code'  => $code,
                'url'   => $a ? $a->getAttribute('href') : '',
            ];

            $marker = $dom->createComment('EKP_META_' . $i);
            if ($h2 && $h2->parentNode) {
                $h2->parentNode->insertBefore($marker, $h2->nextSibling);
            } else {
                $box->insertBefore($marker, $box->firstChild);
            }
            $i++;
        }

        $header = $xp->query('.//div[' . $cls('store-header') . ']', $root)->item(0);
        if ($header && $header->parentNode) {
            $header->parentNode->insertBefore($dom->createComment('EKP_TOP'), $header->nextSibling);
            $out['has_top'] = true;
        }

        $faq = $xp->query('.//div[' . $cls('faq') . ']', $root)->item(0);
        if ($faq && $faq->parentNode) {
            foreach ($xp->query('.//h3', $faq) as $h3) {
                $n = $h3->nextSibling;
                while ($n && $n->nodeType !== XML_ELEMENT_NODE) {
                    $n = $n->nextSibling;
                }
                $q = $clean($h3->textContent);
                $a = ($n && strtolower($n->nodeName) === 'p') ? $clean($n->textContent) : '';
                if ($q !== '' && $a !== '') {
                    $out['faq'][] = [$q, $a];
                }
            }
            $faq->parentNode->insertBefore($dom->createComment('EKP_BEFORE_FAQ'), $faq);
            $faq->appendChild($dom->createComment('EKP_IN_FAQ'));
            $out['has_faq'] = true;
        }

        $html_out = '';
        foreach ($root->childNodes as $child) {
            $html_out .= $dom->saveHTML($child);
        }
        $out['html'] = $html_out;
        return $out;
    }
}

/* Sadržaj stranice iz admina */
$ekp_raw_content = get_the_content();
$ekp_is_vc       = has_shortcode($ekp_raw_content, 'vc_row');
$ekp_content     = str_replace(']]>', ']]&gt;', apply_filters('the_content', $ekp_raw_content));
$ekp             = $ekp_enabled ? ekp_process_content($ekp_content) : ['html' => $ekp_content, 'coupons' => [], 'faq' => [], 'has_faq' => false, 'has_top' => false];
$ekp_coupons     = $ekp['coupons'];

/* Brojač korištenja: JS šalje POST na URL stranice kad netko kopira kod ili klikne na ponudu */
if ($ekp_enabled && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['ekp_track'])) {
    $ekp_key = sanitize_text_field(wp_unslash($_POST['ekp_track']));
    if (in_array($ekp_key, array_column($ekp_coupons, 'key'), true)) {
        $ekp_ip   = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
        $ekp_lock = 'ekp_t_' . md5($ekp_ip . '|' . $ekp_post_id . '|' . $ekp_key);
        if (!get_transient($ekp_lock)) {
            set_transient($ekp_lock, 1, 6 * HOUR_IN_SECONDS);
            $ekp_meta_key = '_ekp_uses_' . md5($ekp_key);
            update_post_meta($ekp_post_id, $ekp_meta_key, (int) get_post_meta($ekp_post_id, $ekp_meta_key, true) + 1);
        }
    }
    nocache_headers();
    status_header(204);
    exit;
}

$ekp_html   = $ekp['html'];
$ekp_schema = null;

if ($ekp_enabled) {
    /* Datumi */
    $ekp_fmt      = $current['date_fmt'] ?? ($t['date_fmt'] ?? 'd.m.Y');
    $ekp_ver_list = [(int) get_post_modified_time('U', true, $ekp_post_id)];
    foreach ([$ekp_settings['last_verified'] ?? '', $current['last_verified'] ?? ''] as $ekp_d) {
        if ($ekp_d && ($ekp_ts = strtotime($ekp_d . ' 12:00:00'))) {
            $ekp_ver_list[] = $ekp_ts;
        }
    }
    $ekp_verified_ts = min(max($ekp_ver_list), time());
    $ekp_verified    = wp_date($ekp_fmt, $ekp_verified_ts);
    $ekp_month_end   = wp_date($ekp_fmt, strtotime(wp_date('Y-m-t') . ' 12:00:00'));
    $ekp_overrides   = $ekp_settings['expiry_overrides'] ?? [];
    $ekp_min_uses    = (int) ($ekp_settings['min_uses_display'] ?? 25);

    foreach ($ekp_coupons as &$ekp_c) {
        $ekp_exp        = ($ekp_c['code'] !== '' && !empty($ekp_overrides[$ekp_c['code']])) ? strtotime($ekp_overrides[$ekp_c['code']] . ' 12:00:00') : false;
        $ekp_c['valid'] = $ekp_exp ? wp_date($ekp_fmt, $ekp_exp) : $ekp_month_end;
        $ekp_c['uses']  = (int) get_post_meta($ekp_post_id, '_ekp_uses_' . md5($ekp_c['key']), true);
    }
    unset($ekp_c);

    /* Urednik */
    $ekp_editor      = $current['editor'] ?? ($ekp_settings['editor'] ?? []);
    $ekp_editor_name = trim($ekp_editor['name'] ?? '') ?: $t['editor_default'];
    $ekp_editor_role = trim($ekp_editor['role'] ?? '');

    $ekp_codes = array_values(array_filter(array_column($ekp_coupons, 'code')));
    $ekp_vars  = [
        'country' => $current['full_name'],
        'date'    => $ekp_verified,
        'editor'  => $ekp_editor_name,
        'code'    => $ekp_codes[0] ?? 'EKUPONI180',
        'codes'   => $ekp_codes ? implode(', ', $ekp_codes) : 'EKUPONI180',
    ];

    /* Link za ponude koje vrijede za sve korisnike (kupon bez koda) */
    $ekp_deal_url = '';
    foreach ($ekp_coupons as $ekp_c) {
        if ($ekp_c['code'] === '' && $ekp_c['url'] !== '') {
            $ekp_deal_url = $ekp_c['url'];
            break;
        }
    }
    if ($ekp_deal_url === '' && !empty($ekp_coupons[0]['url'])) {
        $ekp_deal_url = $ekp_coupons[0]['url'];
    }

    /* FAQ: pitanja sa stranice + dodatna pitanja iz jezičnog paketa */
    $ekp_faq_extra = [];
    $ekp_faq_seen  = [];
    foreach ($ekp['faq'] as $ekp_qa) {
        $ekp_faq_seen[mb_strtolower($ekp_qa[0])] = true;
    }
    foreach ($t['faq'] as $ekp_qa) {
        $ekp_q = ekp_tr($ekp_qa[0], $ekp_vars);
        if (isset($ekp_faq_seen[mb_strtolower($ekp_q)])) {
            continue;
        }
        $ekp_faq_extra[] = [$ekp_q, ekp_tr($ekp_qa[1], $ekp_vars)];
    }
    $ekp_faq_all = array_merge($ekp['faq'], $ekp_faq_extra);

    /* Ostale stranice na istom jeziku (interni linkovi) */
    $ekp_related = [];
    foreach ($COUNTRIES as $ekp_k => $ekp_country) {
        if ($ekp_k === $c_lang || ($ekp_country['lang_pack'] ?? '') !== ($current['lang_pack'] ?? '')) {
            continue;
        }
        $ekp_url = $ekp_country['paths']['kuponi'] ?? '';
        if ($ekp_url && in_array($ekp_url, $ekp_hreflang, true) && $ekp_url !== ($paths['kuponi'] ?? '')) {
            $ekp_related[$ekp_url] = $ekp_country['labels']['cupones'] . ' Temu – ' . $ekp_country['full_name'];
        }
    }

    /* --- Meta red na svakom kuponu --- */
    $ekp_repl = [];
    foreach ($ekp_coupons as $ekp_i => $ekp_c) {
        $ekp_m  = '<div class="ekp-meta">';
        $ekp_m .= '<span class="ekp-badge">✓ ' . esc_html($t['verified']) . '</span>';
        $ekp_m .= '<span>' . esc_html(ekp_tr($t['last_verified'], $ekp_vars)) . '</span>';
        $ekp_m .= '<span>' . esc_html(ekp_tr($t['valid_until'], ['date' => $ekp_c['valid']])) . '</span>';
        if ($ekp_c['uses'] >= $ekp_min_uses) {
            $ekp_m .= '<span class="ekp-uses">' . esc_html(ekp_tr($t['used'], ['n' => number_format_i18n($ekp_c['uses'])])) . '</span>';
        }
        $ekp_m .= '</div>';
        $ekp_repl['<!--EKP_META_' . $ekp_i . '-->'] = $ekp_m;
    }

    /* --- Gornji blok: oznaka provjere + tablica kupona --- */
    ob_start(); ?>
<div class="ekp-trust">
    <span class="ekp-badge">✓ <?php echo esc_html($t['verified']); ?></span>
    <span><?php echo esc_html(ekp_tr($t['last_verified'], $ekp_vars)); ?></span>
    <span><?php echo esc_html(ekp_tr($t['checked_by'], $ekp_vars)); ?></span>
</div>
<a class="ekp-jump" href="#ekp-existing"><?php echo esc_html($t['existing_jump']); ?></a>
<?php if ($ekp_coupons) : ?>
<div class="ekp-panel" id="ekp-overview">
    <h2><?php echo esc_html(ekp_tr($t['table_title'], $ekp_vars)); ?></h2>
    <table class="ekp-table">
        <thead>
            <tr>
                <th scope="col"><?php echo esc_html($t['th_offer']); ?></th>
                <th scope="col"><?php echo esc_html($t['th_code']); ?></th>
                <th scope="col"><?php echo esc_html($t['th_for']); ?></th>
                <th scope="col"><?php echo esc_html($t['th_valid']); ?></th>
                <th scope="col"><?php echo esc_html($t['th_status']); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($ekp_coupons as $ekp_c) : ?>
            <tr data-ekp-key="<?php echo esc_attr($ekp_c['key']); ?>">
                <td class="ekp-td-offer">
                    <strong><?php echo esc_html($ekp_c['title']); ?></strong>
                    <?php if ($ekp_c['desc'] !== '') : ?><br><span class="ekp-small"><?php echo esc_html($ekp_c['desc']); ?></span><?php endif; ?>
                </td>
                <td data-label="<?php echo esc_attr($t['th_code']); ?>">
                    <?php if ($ekp_c['code'] !== '') : ?>
                        <span class="ekp-cell"><span class="ekp-code"><?php echo esc_html($ekp_c['code']); ?></span>
                        <button type="button" class="ekp-copy" data-code="<?php echo esc_attr($ekp_c['code']); ?>" data-copied="<?php echo esc_attr($t['copied']); ?>"><?php echo esc_html($t['copy']); ?></button></span>
                    <?php elseif ($ekp_c['url'] !== '') : ?>
                        <a class="ekp-link" href="<?php echo esc_url($ekp_c['url']); ?>" target="_blank" rel="noopener sponsored"><?php echo esc_html($t['no_code']); ?> – <?php echo esc_html($t['get']); ?></a>
                    <?php else : ?>
                        <?php echo esc_html($t['no_code']); ?>
                    <?php endif; ?>
                </td>
                <td data-label="<?php echo esc_attr($t['th_for']); ?>"><?php echo esc_html($ekp_c['code'] !== '' ? $t['for_new'] : $t['for_all']); ?></td>
                <td data-label="<?php echo esc_attr($t['th_valid']); ?>"><?php echo esc_html($ekp_c['valid']); ?></td>
                <td data-label="<?php echo esc_attr($t['th_status']); ?>">
                    <span class="ekp-cell"><span class="ekp-ok">✓ <?php echo esc_html($t['verified']); ?></span><?php if ($ekp_c['uses'] >= $ekp_min_uses) : ?><br><span class="ekp-small"><?php echo esc_html(ekp_tr($t['used'], ['n' => number_format_i18n($ekp_c['uses'])])); ?></span><?php endif; ?></span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif;
    $ekp_top = ob_get_clean();

    /* --- Donji blok: upute, stari korisnici, kategorije, savjet urednika, linkovi --- */
    ob_start(); ?>
<div class="ekp-panel" id="ekp-howto">
    <h2><?php echo esc_html($t['howto_title']); ?></h2>
    <ol class="ekp-steps">
        <?php foreach ($t['steps'] as $ekp_step) : ?>
            <li><?php echo esc_html(ekp_tr($ekp_step, $ekp_vars)); ?></li>
        <?php endforeach; ?>
    </ol>
    <h3><?php echo esc_html($t['timing_title']); ?></h3>
    <ul class="ekp-steps">
        <?php foreach ($t['timing'] as $ekp_tip) : ?>
            <li><?php echo esc_html(ekp_tr($ekp_tip, $ekp_vars)); ?></li>
        <?php endforeach; ?>
    </ul>
    <p class="ekp-small"><?php echo esc_html($t['timing_note']); ?></p>
</div>

<div class="ekp-panel" id="ekp-existing">
    <h2><?php echo esc_html($t['existing_title']); ?></h2>
    <p><?php echo esc_html(ekp_tr($t['existing_intro'], $ekp_vars)); ?></p>
    <div class="ekp-grid">
        <?php foreach ($t['existing'] as $ekp_item) : ?>
            <div class="ekp-card">
                <h3><?php echo esc_html($ekp_item[0]); ?></h3>
                <p><?php echo esc_html($ekp_item[1]); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if ($ekp_deal_url !== '') : ?>
        <a class="shop-btn ekp-cta" href="<?php echo esc_url($ekp_deal_url); ?>" target="_blank" rel="noopener sponsored"><?php echo esc_html($t['existing_cta']); ?></a>
    <?php endif; ?>
</div>

<div class="ekp-panel" id="ekp-categories">
    <h2><?php echo esc_html($t['cat_title']); ?></h2>
    <p><?php echo esc_html($t['cat_intro']); ?></p>
    <table class="ekp-cat-table">
        <thead>
            <tr>
                <th scope="col"><?php echo esc_html($t['th_cat']); ?></th>
                <th scope="col"><?php echo esc_html($t['th_tip']); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($t['cats'] as $ekp_cat) : ?>
            <tr>
                <th scope="row"><?php echo esc_html($ekp_cat[0]); ?></th>
                <td><?php echo esc_html($ekp_cat[1]); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="ekp-panel ekp-tip" id="ekp-editor">
    <h2><?php echo esc_html($t['editor_title']); ?></h2>
    <blockquote><?php echo esc_html($t['editor_text']); ?></blockquote>
    <div class="ekp-author">
        <span class="ekp-avatar" aria-hidden="true"><?php echo esc_html(mb_strtoupper(mb_substr($ekp_editor_name, 0, 1))); ?></span>
        <span>
            <strong><?php echo esc_html($ekp_editor_name); ?></strong><?php if ($ekp_editor_role !== '') : ?>, <?php echo esc_html($ekp_editor_role); ?><?php endif; ?><br>
            <span class="ekp-small"><?php echo esc_html(ekp_tr($t['last_verified'], $ekp_vars)); ?></span>
        </span>
    </div>
</div>

<div class="ekp-panel" id="ekp-links">
    <h2><?php echo esc_html($t['links_title']); ?></h2>
    <ul class="ekp-links">
        <?php if (!empty($paths['vodic'])) : ?>
            <li><a href="<?php echo esc_url($paths['vodic']); ?>"><?php echo esc_html(ekp_tr($t['link_guide'], $ekp_vars)); ?></a></li>
        <?php endif; ?>
        <li><a href="#ekp-existing"><?php echo esc_html($t['link_existing']); ?></a></li>
        <li><a href="#ekp-howto"><?php echo esc_html($t['link_howto']); ?></a></li>
        <?php foreach ($ekp_related as $ekp_url => $ekp_label) : ?>
            <li><a href="<?php echo esc_url($ekp_url); ?>"><?php echo esc_html($ekp_label); ?></a></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php
    $ekp_bottom = ob_get_clean();

    /* --- Dodatna FAQ pitanja --- */
    $ekp_faq_html = '';
    foreach ($ekp_faq_extra as $ekp_qa) {
        $ekp_faq_html .= '<h3>' . esc_html($ekp_qa[0]) . '</h3><p>' . esc_html($ekp_qa[1]) . '</p>';
    }

    /* --- Ubacivanje u sadržaj --- */
    $ekp_repl['<!--EKP_TOP-->']        = $ekp_top;
    $ekp_repl['<!--EKP_BEFORE_FAQ-->'] = $ekp_bottom;
    $ekp_repl['<!--EKP_IN_FAQ-->']     = $ekp_faq_html;
    $ekp_html = strtr($ekp_html, $ekp_repl);

    if (!$ekp['has_top']) {
        $ekp_html = $ekp_top . $ekp_html;
    }
    if (!$ekp['has_faq']) {
        $ekp_html .= $ekp_bottom;
        if ($ekp_faq_html !== '') {
            $ekp_html .= '<div class="faq"><h2>' . esc_html($t['faq_title']) . '</h2>' . $ekp_faq_html . '</div>';
        }
    }

    /* --- Strukturirani podaci --- */
    $ekp_permalink = get_permalink($ekp_post_id);
    $ekp_publisher = ['@type' => 'Organization', 'name' => 'eKuponi', 'url' => 'https://ekuponi.com/'];
    $ekp_graph     = [[
        '@type'        => 'WebPage',
        '@id'          => $ekp_permalink . '#webpage',
        'url'          => $ekp_permalink,
        'name'         => $c_title,
        'inLanguage'   => $current['seo_code'],
        'dateModified' => wp_date('c', $ekp_verified_ts),
        'author'       => ($ekp_editor_name !== $t['editor_default']) ? ['@type' => 'Person', 'name' => $ekp_editor_name] : $ekp_publisher,
        'publisher'    => $ekp_publisher,
    ]];
    if ($ekp_faq_all) {
        $ekp_main = [];
        foreach ($ekp_faq_all as $ekp_qa) {
            $ekp_main[] = ['@type' => 'Question', 'name' => $ekp_qa[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $ekp_qa[1]]];
        }
        $ekp_graph[] = ['@type' => 'FAQPage', '@id' => $ekp_permalink . '#faq', 'inLanguage' => $current['seo_code'], 'mainEntity' => $ekp_main];
    }
    $ekp_steps = [];
    foreach (array_values($t['steps']) as $ekp_n => $ekp_step) {
        $ekp_steps[] = ['@type' => 'HowToStep', 'position' => $ekp_n + 1, 'text' => ekp_tr($ekp_step, $ekp_vars)];
    }
    $ekp_graph[] = ['@type' => 'HowTo', '@id' => $ekp_permalink . '#howto', 'name' => $t['howto_title'], 'inLanguage' => $current['seo_code'], 'step' => $ekp_steps];
    $ekp_schema  = ['@context' => 'https://schema.org', '@graph' => $ekp_graph];
}

?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($c_lang); ?>">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <meta name="robots" content="index, follow">

 <title><?php echo esc_html($c_title); ?></title>
 <?php if ($c_desc): ?>
 <meta name="description" content="<?php echo esc_attr($c_desc); ?>">
 <?php endif; ?>

 <link rel="canonical" href="<?php echo get_permalink(); ?>" />

 <style>
 body { margin: 0; padding: 0; font-family: Arial, sans-serif; background: linear-gradient(to bottom, #E89C3F, #C67D2F); min-height: 100vh; }
 .glsr-star-full { background-image: url("https://ekuponi.com/wp-content/plugins/site-reviews/assets/images/stars/default/star-full.svg") !important; }
 .glsr-star-half { background-image: url("https://ekuponi.com/wp-content/plugins/site-reviews/assets/images/stars/default/star-half.svg") !important; }
 .glsr-summary { justify-content: center; }
 .glsr-star-rating { margin-right: 15px; }
 h1 { text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3); text-align: center; color: white; margin-top: 40px; }

        /* Country Selector Styles */
        .country-selector-wrapper { background: transparent; padding: 40px 20px; margin-top: 30px; }
        .country-selector-container { max-width: 300px; margin: 0 auto; text-align: center; }
        .custom-select-wrapper { position: relative; width: 100%; max-width: 250px; margin: 0 auto; }
        .custom-select-trigger { width: 100%; padding: 12px 15px; font-size: 16px; border: 2px solid #ddd; border-radius: 8px; background: white; cursor: pointer; text-align: left; transition: border-color 0.3s; display: flex; align-items: center; gap: 10px; box-sizing: border-box; }
        .custom-select-trigger.active { border-color: #0066cc; border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
        .custom-select-trigger::after { content: ''; position: absolute; right: 15px; top: 50%; transform: translateY(-50%); width: 0; height: 0; border-left: 6px solid transparent; border-right: 6px solid transparent; border-top: 6px solid #333; transition: transform 0.3s; }
        .custom-select-trigger.active::after { transform: translateY(-50%) rotate(180deg); }
        .custom-select-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 2px solid #0066cc; border-top: none; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; max-height: 300px; overflow-y: auto; display: none; z-index: 1000; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .custom-select-dropdown.active { display: block; }
        .custom-select-option { padding: 12px 15px; cursor: pointer; display: flex; align-items: center; gap: 10px; transition: background-color 0.2s; text-align: left; }
        .custom-select-option:hover { background-color: #f0f0f0; }
        .flag-icon { width: 24px; height: 18px; object-fit: cover; border-radius: 2px; flex-shrink: 0; }
        
        
        .main-content { width: 100%; }
        .page-container.ptb-60 { padding-top: 60px; padding-bottom: 60px; }
        .klb-post.ptb-30 { padding-top: 30px; padding-bottom: 30px; }
        .container { max-width: 1140px; margin: 0 auto; padding: 0 15px; }
        .panel { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        
        
        .coupon-box { 
    border: 2px dashed #ff9800; 
    padding: 20px; 
    width: auto; 
    max-width: 840px; 
    margin: 20px 15px; 
    background: #fff8e1; 
    box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1); 
    border-radius: 8px; 
    text-align: center;
    box-sizing: border-box; 
    }
    
    footer {
    background-color: rgba(0, 0, 0, 0.15);
    padding: 30px 20px 20px;
    color: #fff;
    font-size: 13px;
    line-height: 1.6;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
}

.footer-container {
    max-width: 1200px;
    margin: 0 auto;
}

.affiliate-notice {
    background-color: rgba(255, 255, 255, 0.1);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
}

.affiliate-notice strong {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.footer-links {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 15px;
}

.footer-links a {
    color: #fff;
    text-decoration: none;
    transition: opacity 0.2s;
}

.footer-links a:hover {
    opacity: 0.8;
    text-decoration: underline;
}

.footer-links span {
    color: rgba(255, 255, 255, 0.5);
}

.copyright {
    text-align: center;
    color: rgba(255, 255, 255, 0.8);
    font-size: 12px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
}

@media (max-width: 768px) {
    footer {
        font-size: 12px;
    }

    .footer-links {
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }

    .footer-links span {
        display: none;
    }

    .affiliate-notice {
        font-size: 12px;
    }
}


@media (min-width: 768px) {
    .coupon-box {
        margin: 20px auto;
        width: 70%;
    }
}
        .coupon-box:hover { box-shadow: 0px 6px 10px rgba(0, 0, 0, 0.2); }
        
        .code-box { 
            background: #fff; 
            border: 2px solid #31bda4; 
            padding: 10px; 
            font-size: 18px; 
            font-weight: bold; 
            color: #31bda4; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-radius: 5px; 
            width: 80%; 
            max-width: 250px; 
            margin: 10px auto; 
        }
        
        .copy-btn { background: #31bda4; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 5px; font-weight: bold; }
        
        .shop-btn { 
            background: #ff9800; 
            color: white !important; 
            font-weight: 700; 
            padding: 12px 15px; 
            border-radius: 5px; 
            text-decoration: none; 
            display: block; 
            width: 80%; 
            max-width: 180px; 
            margin: 10px auto; 
        }
        
        .copied-message { display: none; color: #31bda4; font-size: 14px; margin-top: 5px; font-weight: bold; }

        /* FAQ Section */
        .faq { 
            width: 85%; 
            max-width: 700px; 
            margin: 30px auto; 
            text-align: left; 
            background: #fff; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        }
        .faq h2 { color: #ff9800; margin-top: 0; }
        .faq h3 { color: #333; font-size: 18px; margin-bottom: 5px; }

        
        .store-header { text-align: center; padding: 20px 0; }
        .store-header h1 { font-size: 42px; margin-bottom: 10px; }
        .store-header h2 { font-size: 32px; font-weight: bold; color: #fff; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); }

        /* ===== SEO sekcije (template) ===== */
        .ekp-trust { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 8px 16px; color: #fff; font-size: 14px; text-shadow: 1px 1px 2px rgba(0,0,0,0.3); margin: 0 15px 5px; }
        .ekp-badge { background: #31bda4; color: #fff; border-radius: 20px; padding: 3px 10px; font-weight: 700; font-size: 13px; text-shadow: none; white-space: nowrap; }
        .ekp-jump { display: block; text-align: center; margin: 12px 15px; color: #fff; font-weight: 700; text-shadow: 1px 1px 2px rgba(0,0,0,0.3); }
        .ekp-panel { background: #fff; border-radius: 8px; box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1); padding: 20px; max-width: 840px; margin: 20px 15px; box-sizing: border-box; text-align: left; color: #333; line-height: 1.5; }
        .ekp-panel h2 { color: #ff9800; margin-top: 0; font-size: 24px; }
        .ekp-panel h3 { color: #333; font-size: 18px; margin: 18px 0 6px; }
        .ekp-small { font-size: 13px; color: #777; }
        .ekp-meta { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 12px; font-size: 13px; color: #555; margin: 6px 0 12px; font-weight: normal; }
        .ekp-uses { font-weight: 700; color: #e65100; }
        .ekp-table, .ekp-cat-table { width: 100%; border-collapse: collapse; font-size: 15px; }
        .ekp-table th, .ekp-table td, .ekp-cat-table th, .ekp-cat-table td { border-bottom: 1px solid #eee; padding: 10px 8px; text-align: left; vertical-align: top; }
        .ekp-table thead th, .ekp-cat-table thead th { background: #fff3e0; color: #333; font-size: 14px; }
        .ekp-cat-table tbody th { width: 32%; color: #333; }
        .ekp-code { display: inline-block; font-family: monospace; font-weight: 700; font-size: 15px; color: #31bda4; border: 2px dashed #31bda4; padding: 2px 6px; border-radius: 4px; margin: 0 4px 4px 0; }
        .ekp-copy { background: #31bda4; color: #fff; border: none; padding: 4px 10px; cursor: pointer; border-radius: 5px; font-weight: 700; font-size: 13px; }
        .ekp-link { color: #e65100; font-weight: 700; }
        .ekp-cell { display: inline-block; }
        .ekp-ok { color: #31bda4; font-weight: 700; white-space: nowrap; }
        .ekp-steps { padding-left: 22px; margin: 0; }
        .ekp-steps li { margin-bottom: 8px; }
        .ekp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin: 12px 0; }
        .ekp-card { background: #fff8e1; border: 1px solid #ffe0b2; border-radius: 8px; padding: 12px; }
        .ekp-card h3 { margin: 0 0 6px; font-size: 17px; }
        .ekp-card p { margin: 0; font-size: 15px; }
        .shop-btn.ekp-cta { max-width: 320px; text-align: center; }
        .ekp-tip { border-left: 6px solid #31bda4; }
        .ekp-tip blockquote { margin: 0; font-size: 16px; font-style: italic; }
        .ekp-author { display: flex; align-items: center; gap: 10px; margin-top: 14px; font-size: 14px; }
        .ekp-avatar { width: 40px; height: 40px; border-radius: 50%; background: #ff9800; color: #fff; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .ekp-links { padding-left: 20px; margin: 0; }
        .ekp-links li { margin-bottom: 6px; }
        .ekp-links a { color: #e65100; }
        @media (min-width: 768px) {
            .ekp-panel { margin: 20px auto; width: 70%; }
        }
        @media (max-width: 640px) {
            .ekp-table thead { display: none; }
            .ekp-table, .ekp-table tbody, .ekp-table tr, .ekp-table td { display: block; width: 100%; box-sizing: border-box; }
            .ekp-table tr { border: 1px solid #eee; border-radius: 8px; margin-bottom: 12px; padding: 6px 10px; }
            .ekp-table td { border: none; padding: 6px 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; text-align: right; }
            .ekp-table td::before { content: attr(data-label); font-weight: 700; color: #555; text-align: left; }
            .ekp-table td.ekp-td-offer { display: block; text-align: left; border-bottom: 1px solid #eee; padding-bottom: 8px; }
            .ekp-table td.ekp-td-offer::before { content: none; }
            .ekp-cat-table tbody th { width: auto; }
        }
    </style>

<script>
    window.addEventListener('load', function() {
        var gtmScript = document.createElement('script');
        gtmScript.src = 'https://www.googletagmanager.com/gtag/js?id=G-FG5B4R133J';
        document.head.appendChild(gtmScript);
        
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-FG5B4R133J');
    });
</script>

<?php foreach ($ekp_hreflang as $ekp_lang => $ekp_href) : ?>
<link rel="alternate" hreflang="<?php echo esc_attr($ekp_lang); ?>" href="<?php echo esc_url($ekp_href); ?>" />
<?php endforeach; ?>

<?php if ($ekp_schema) : ?>
<script type="application/ld+json"><?php echo wp_json_encode($ekp_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?></script>
<?php endif; ?>

</head>
<body>

<?php if ($ekp_is_vc) {
    echo $ekp_html;
} else { ?>
        <main id="mainContent" class="main-content">
            <div class="page-container ptb-0">
                <div class="container">
                    <section>
                        <div class="klb-post ptb-0 prl-30">
                            <?php echo $ekp_html; ?>
                            <?php wp_link_pages(array('before' => '<div class="klb-pagination">Pages:','after' => '</div>')); ?>
                        </div>
                    </section>
                </div>
            </div>
        </main>
<?php } ?>

<div class="country-selector-wrapper">
    <div class="country-selector-container">
        
        <div class="custom-select-wrapper">
            <div class="custom-select-trigger" id="selectTrigger">
                <img src="<?php echo esc_url($current['flag_url']); ?>" alt="<?php echo esc_attr($current['full_name']); ?>" loading="lazy" class="flag-icon">
                <span><?php echo esc_html($current['full_name']); ?></span>
            </div>
            <div class="custom-select-dropdown" id="selectDropdown">
                <a class="custom-select-option" href="https://ekuponi.com/cupones-y-codigos-descuento-temu-argentina/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/ar.png" alt="Argentina" loading="lazy" class="flag-icon">
                    <span>Argentina</span>
                </a>
                <a class="custom-select-option" href="https://ekuponi.com/cupones-y-codigos-descuento-temu-colombia/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/co.png" alt="Colombia" loading="lazy" class="flag-icon">
                    <span>Colombia</span>
                </a>
                <a class="custom-select-option" href="https://ekuponi.com/store/temu/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/hr.png" alt="Croatia" loading="lazy" class="flag-icon">
                    <span>Croatia</span>
                </a>
                <a class="custom-select-option" href="https://ekuponi.com/temu-kupony-a-slevove-kody/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/cz.png" alt="Czech Republic" loading="lazy" class="flag-icon">
                    <span>Czech Republic</span>
                </a>
                <a class="custom-select-option" href="https://ekuponi.com/cupones-y-codigos-descuento-temu-republica-dominicana/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/do.png" alt="Dominican Republic" loading="lazy" class="flag-icon">
                    <span>Dominican Republic</span>
                </a>
                <a class="custom-select-option" href="https://ekuponi.com/cupones-y-codigos-descuento-temu-el-salvador/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/sv.png" alt="El Salvador" loading="lazy" class="flag-icon">
                    <span>El Salvador</span>
                </a>
                <a class="custom-select-option" href="https://ekuponi.com/code-promo-temu/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/fr.png" alt="France" loading="lazy" class="flag-icon">
                    <span>France</span>
                </a>
                <a class="custom-select-option" href="https://ekuponi.com/temu-gutscheincode/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/de.png" alt="Germany" loading="lazy" class="flag-icon">
                    <span>Germany</span>
                </a>
                <a class="custom-select-option" href="https://ekuponi.com/%ce%ba%cf%89%ce%b4%ce%b9%ce%ba%ce%bf%ce%af-%ce%ad%ce%ba%cf%80%cf%84%cf%89%cf%83%ce%b7%cf%82-%ce%ba%ce%bf%cf%85%cf%80%cf%8c%ce%bd%ce%b9%ce%b1-temu/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/gr.png" alt="Greece" loading="lazy" class="flag-icon">
                    <span>Greece</span>
                </a>
                <a class="custom-select-option" href="https://ekuponi.com/temu-kuponok-es-akciok/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/hu.png" alt="Hungary" loading="lazy" class="flag-icon">
                    <span>Hungary</span>
                </a>
                <a class="custom-select-option" href="https://ekuponi.com/buoni-sconto-e-codici-sconto-temu/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/it.png" alt="Italy" loading="lazy" class="flag-icon">
                    <span>Italy</span>
                </a>
                <a class="custom-select-option" href="https://ekuponi.com/cupones-y-codigos-descuento-temu-mexico/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/mx.png" alt="Mexico" loading="lazy" class="flag-icon">
                    <span>Mexico</span>
                </a>
                <a class="custom-select-option" href="https://ekuponi.com/temu-kortingscodes-coupons/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/nl.png" alt="Netherlands" loading="lazy" class="flag-icon">
                    <span>Netherlands</span>
                </a>
                <a class="custom-select-option" href="https://ekuponi.com/cupones-y-codigos-descuento-temu-peru/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/pe.png" alt="Peru" loading="lazy" class="flag-icon">
                    <span>Peru</span>
                </a>
                <a class="custom-select-option" href="https://ekuponi.com/temu-kupony-i-kody-rabatowe/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/pl.png" alt="Poland" loading="lazy" class="flag-icon">
                    <span>Poland</span>
                </a>
                <a class="custom-select-option" href="https://ekuponi.com/codigos-de-desconto-cupoes-temu/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/pt.png" alt="Portugal" loading="lazy" class="flag-icon">
                    <span>Portugal</span>
                </a>
                <a class="custom-select-option" href="https://ekuponi.com/temu-reducere-si-cupoane/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/ro.png" alt="Romania" loading="lazy" class="flag-icon">
                    <span>Romania</span>
                </a>
                <a class="custom-select-option" href="https://ekuponi.com/cupones-y-codigos-descuento-temu/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/es.png" alt="Spain" loading="lazy" class="flag-icon">
                    <span>Spain</span>
                </a>
                <a class="custom-select-option" href="https://ekuponi.com/temu-rabattkoder-kuponger/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/se.png" alt="Sweden" loading="lazy" class="flag-icon">
                    <span>Sweden</span>
                </a>
                <a class="custom-select-option" href="https://ekuponi.com/temu-%d0%ba%d1%83%d0%bf%d0%be%d0%bd%d0%b8-%d1%82%d0%b0-%d0%bf%d1%80%d0%be%d0%bc%d0%be%d0%ba%d0%be%d0%b4%d0%b8-%d0%b4%d0%be-70/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/ua.png" alt="Ukraine" loading="lazy" class="flag-icon">
                    <span>Ukraine</span>
                </a>
                <a class="custom-select-option" href="https://ekuponi.com/temu-coupon-codes-uk/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/gb.png" alt="United Kingdom" loading="lazy" class="flag-icon">
                    <span>United Kingdom</span>
                </a>
                <a class="custom-select-option" href="https://ekuponi.com/temu-coupon-codes-us/" style="text-decoration: none; color: inherit;">
                    <img src="https://flagcdn.com/w40/us.png" alt="United States" loading="lazy" class="flag-icon">
                    <span>United States</span>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const trigger = document.getElementById('selectTrigger');
    const dropdown = document.getElementById('selectDropdown');
    

    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        this.classList.toggle('active');
        dropdown.classList.toggle('active');
    });
    

    document.addEventListener('click', function() {
        trigger.classList.remove('active');
        dropdown.classList.remove('active');
    });


});

function copyCode(codeId, messageId) {
    var code = document.getElementById(codeId).innerText;
    navigator.clipboard.writeText(code).then(function() {
        var message = document.getElementById(messageId);
        message.style.display = "block";
        setTimeout(function() { message.style.display = "none"; }, 2000);
    });
}
</script>

<script>
/* Kopiranje koda iz tablice + brojač korištenja (jedan klik po kodu po pregledniku u 12 h) */
(function () {
    function ekpTrack(key) {
        if (!key) return;
        try {
            var lsKey = 'ekp_t_' + key, last = parseInt(localStorage.getItem(lsKey) || '0', 10);
            if (Date.now() - last < 12 * 3600 * 1000) return;
            localStorage.setItem(lsKey, String(Date.now()));
        } catch (e) {}
        var url = window.location.href.split('#')[0];
        var data = new FormData();
        data.append('ekp_track', key);
        if (navigator.sendBeacon) {
            navigator.sendBeacon(url, data);
        } else if (window.fetch) {
            fetch(url, { method: 'POST', body: data, keepalive: true, credentials: 'same-origin' });
        }
    }

    document.addEventListener('click', function (e) {
        var target = e.target;
        if (!target || !target.closest) return;

        var copyBtn = target.closest('.ekp-copy');
        if (copyBtn) {
            var code = copyBtn.getAttribute('data-code');
            var original = copyBtn.textContent;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(code).then(function () {
                    copyBtn.textContent = copyBtn.getAttribute('data-copied') || original;
                    setTimeout(function () { copyBtn.textContent = original; }, 2000);
                });
            }
        }

        var box = target.closest('[data-ekp-key]');
        if (box && target.closest('a, button')) {
            ekpTrack(box.getAttribute('data-ekp-key'));
        }
    });
})();
</script>

<link rel="stylesheet" href="https://ekuponi.com/wp-content/plugins/site-reviews/assets/styles/minimal.css" media="print" onload="this.media='all'">

<footer>
    <div class="footer-container">
        
        <div class="affiliate-notice">
            <strong><?php echo esc_html($labels['affiliate_title']); ?></strong>
            <p><?php echo esc_html($labels['affiliate_text']); ?></p>
        </div>

        <div class="footer-links">
            <a href="https://ekuponi.com/privacy-policy/" target="_blank"><?php echo esc_html($labels['privacy']); ?></a>
            <span>|</span>
            <a href="https://ekuponi.com/terms-of-service/" target="_blank"><?php echo esc_html($labels['terms']); ?></a>
            <span>|</span>
            <a href="mailto:info@ekuponi.com"><?php echo esc_html($labels['contacto']); ?></a>
        </div>

        <div class="copyright">
            <?php echo esc_html($labels['copyright']); ?>
        </div>

    </div>
</footer>
</body>
</html>
