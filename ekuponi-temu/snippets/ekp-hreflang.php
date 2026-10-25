<?php
/**
 * eKuponi – hreflang za trgovine (/store/...) i naslovnice na svim podstranicama mreže.
 *
 * Postavljanje: Network Admin → Snippets → Add New → zalijepi kod (bez "<?php" retka)
 * → "Only run on site front-end" → Save → "Network Activate".
 *
 * - Temu (/store/temu/): puna grupa – HR/SI/BG/RS/BA + sve državne Temu stranice + x-default.
 *   Isti popis koristi i single-store-page.php (preko ekp_temu_hreflang()).
 * - Ostale trgovine: verzije iste trgovine na HR/SI/BG/RS/BA, samo tamo gdje trgovina postoji.
 * - Naslovnice: HR/SI/BG/RS/BA.
 */

if (!function_exists('ekp_hreflang_sites')) {
    /* hreflang => [putanja podstranice u multisiteu, javni URL te verzije] */
    function ekp_hreflang_sites() {
        return [
            'hr'    => ['/',    'https://ekuponi.com/'],
            'sl-SI' => ['/sl/', 'https://sl.ekuponi.com/sl/'],
            'bg-BG' => ['/bg/', 'https://bg.ekuponi.com/bg/'],
            'sr-RS' => ['/rs/', 'https://ekuponi.com/rs/'],
            'bs-BA' => ['/ba/', 'https://ekuponi.com/ba/'],
        ];
    }
}

if (!function_exists('ekp_temu_hreflang')) {
    function ekp_temu_hreflang() {
        $out = [];
        foreach (ekp_hreflang_sites() as $lang => $site) {
            $out[$lang] = $site[1] . 'store/temu/';
        }
        return $out + [
            'es-AR' => 'https://ekuponi.com/cupones-y-codigos-descuento-temu-argentina/',
            'es-CO' => 'https://ekuponi.com/cupones-y-codigos-descuento-temu-colombia/',
            'es-CL' => 'https://ekuponi.com/cupones-y-codigos-descuento-temu-chile/',
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
    }
}

if (!function_exists('ekp_store_hreflang')) {
    /* Verzije trgovine na podstranicama gdje postoji (rezultat se pamti 1 dan). */
    function ekp_store_hreflang($slug) {
        $key    = 'ekp_hl_' . md5($slug);
        $cached = get_site_transient($key);
        if (is_array($cached)) {
            return $cached;
        }
        $out    = [];
        $domain = get_network()->domain;
        foreach (ekp_hreflang_sites() as $lang => $site) {
            $blog_id = get_blog_id_from_url($domain, $site[0]);
            if (!$blog_id) {
                continue;
            }
            switch_to_blog($blog_id);
            $exists = term_exists($slug, 'store');
            restore_current_blog();
            if ($exists) {
                $out[$lang] = $site[1] . 'store/' . $slug . '/';
            }
        }
        set_site_transient($key, $out, DAY_IN_SECONDS);
        return $out;
    }
}

add_action('wp_head', function () {
    if (is_paged()) {
        return;
    }
    $tags = [];
    if (is_tax('store')) {
        $slug = get_queried_object()->slug;
        $tags = $slug === 'temu' ? ekp_temu_hreflang() : ekp_store_hreflang($slug);
    } elseif (is_front_page()) {
        foreach (ekp_hreflang_sites() as $lang => $site) {
            $tags[$lang] = $site[1];
        }
        $tags['x-default'] = 'https://ekuponi.com/';
    }
    if (count($tags) < 2) {
        return;
    }
    foreach ($tags as $lang => $href) {
        echo '<link rel="alternate" hreflang="' . esc_attr($lang) . '" href="' . esc_url($href) . '" />' . "\n";
    }
}, 2);
