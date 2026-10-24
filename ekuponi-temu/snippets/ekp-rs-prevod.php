<?php
/**
 * eKuponi – prijevod hrvatskih/slovenskih/engleskih izraza na srpski (ekavica, latinica) na /rs podstranici.
 *
 * Postavljanje: zalijepi na kraj functions.php child teme (bez "<?php" retka).
 * Mijenja se samo vidljivi tekst i meta title/description – linkovi, slugovi, skripte i stilovi ostaju netaknuti.
 * Novi izraz se dodaje u ekp_rs_map() kao 'original' => 'prijevod'.
 */

if (!defined('EKP_RS_BLOG_ID')) {
    define('EKP_RS_BLOG_ID', 16);
}

if (!function_exists('ekp_rs_map')) {
    function ekp_rs_map() {
        return [
            // Cijele rečenice (imaju prednost pred pojedinačnim riječima)
            'Temu je web trgovina koja nudi razne proizvode po pristupačnim cijenama, od odjeće i obuće do kozmetičkih i kućnih dekoracija.'
                => 'Temu je onlajn prodavnica koja nudi razne proizvode po pristupačnim cenama, od odeće i obuće do kozmetike i kućnih dekoracija.',
            'eKuponi.com je tražilica kupona.' => 'eKuponi.com je pretraživač kupona.',
            'Samo upišite u tražilicu što Vas zanima, kopirajte kod kupona i zalijepite u web trgovini.'
                => 'Samo upišite u pretraživač šta Vas zanima, kopirajte kod kupona i nalepite ga u onlajn prodavnici.',
            'Sva prava pridržana' => 'Sva prava zadržana',
            '(gdje je primjenjivo)' => '(gde je primenljivo)',
            'uređaj ili račun' => 'uređaj ili nalog',
            'u bilo kojem trenutku' => 'u bilo kom trenutku',
            'ograničeno trajanje valjanosti' => 'ograničen rok važenja',
            'besplatni dar ' => 'besplatni poklon ',
            ' te imaju ' => ' i imaju ',
            ' s drugim ' => ' sa drugim ',
            ' s другим ' => ' sa drugim ',   // ćirilica upisana greškom u sadržaju
            'prethodnih kupnji' => 'prethodnih kupovina',
            'cijeli asortiman' => 'ceo asortiman',
            'ovisi o trenutnim zalihama' => 'zavisi od trenutnih zaliha',
            'ovisno o regiji isporuke' => 'u zavisnosti od regiona isporuke',

            // Slovenski izrazi iz teme
            'POTEK NEDOLOČEN' => 'BEZ ROKA VAŽENJA',
            'Potek nedoločen' => 'Bez roka važenja',
            'Poglej akcijo'   => 'Pogledaj akciju',
            'Poglej kupon'    => 'Pogledaj kupon',
            'Pokaži kodo'     => 'Prikaži kod',
            'Kopiraj kodo'    => 'Kopiraj kod',
            'Potek:'          => 'Ističe:',
            'Hrvaška'         => 'Hrvatska',

            // Engleski izrazi (Site Reviews obrazac)
            'Your overall rating' => 'Vaša ukupna ocena',
            'Select a Rating'     => 'Izaberite ocenu',
            '5 Stars' => '5 zvezdica',
            '4 Stars' => '4 zvezdice',
            '3 Stars' => '3 zvezdice',
            '2 Stars' => '2 zvezdice',
            '1 Star'  => '1 zvezdica',
            'This review is based on my own experience and is my genuine opinion.'
                => 'Ova recenzija je zasnovana na mom ličnom iskustvu i predstavlja moje iskreno mišljenje.',
            'Submit your review' => 'Pošaljite recenziju',
            'Title of your review' => 'Naslov recenzije',
            'Your review' => 'Vaša recenzija',
            'Your name'   => 'Vaše ime',
            'Your email'  => 'Vaš e-mail',

            // Izbornik i podnožje
            'POVEZNICE' => 'LINKOVI',
            'MENU'      => 'MENI',
            'na temelju' => 'na osnovu',
            'osvrta)'   => 'recenzija)',

            // Države
            'Španjolsk' => 'Španij',
            'Njemačk'   => 'Nemačk',

            // Riječi (hrvatski → srpski); dulji izraz uvijek ima prednost
            'web trgovin' => 'onlajn prodavnic',
            'Trgovin'   => 'Prodavnic',
            'trgovin'   => 'prodavnic',
            'tražilicu' => 'pretraživač',
            'tražilica' => 'pretraživač',
            'Narudžb'   => 'Porudžbin',
            'narudžb'   => 'porudžbin',
            'kupnj'     => 'kupovin',
            'Uvjet'     => 'Uslov',
            'uvjet'     => 'uslov',
            'korištenj' => 'korišćenj',
            'kombinir'  => 'kombinov',
            'izračun'   => 'obračun',
            'darovi'    => 'pokloni',
            'namijenj'  => 'namenj',
            'primjen'   => 'primen',
            'izmjen'    => 'izmen',
            'zalijep'   => 'zalep',
            'zvjezdic'  => 'zvezdic',
            'cijen'     => 'cen',
            'Vrijedi'   => 'Važi',
            'Vrijede'   => 'Važe',
            'Sljede'    => 'Slede',
            'sljede'    => 'slede',
            'primitka'  => 'prijema',
            'izravno'   => 'direktno',
            'kvalificiran' => 'kvalifikovan',
            'darova'    => 'poklona',
            'pristojb'  => 'taks',
            'odjeć'     => 'odeć',
            'mjesec'    => 'mesec',
            'mjest'     => 'mest',
            'vrijeme'   => 'vreme',
            'vrijedn'   => 'vredn',
            'vrijedi'   => 'važi',
            'vrijede'   => 'važe',
            'uvijek'    => 'uvek',
            'tisuć'     => 'hiljad',
            'OVDJE'     => 'OVDE',
            'Ovdje'     => 'Ovde',
            'ovdje'     => 'ovde',
            'Gdje'      => 'Gde',
            'gdje'      => 'gde',

            // Mjeseci (WordPress ih na sr_RS ispisuje ćirilicom)
            'јануар' => 'januar', 'фебруар' => 'februar', 'март' => 'mart', 'април' => 'april',
            'мај' => 'maj', 'јун' => 'jun', 'јул' => 'jul', 'август' => 'avgust',
            'септембар' => 'septembar', 'октобар' => 'oktobar', 'новембар' => 'novembar', 'децембар' => 'decembar',
            'Јануар' => 'Januar', 'Фебруар' => 'Februar', 'Март' => 'Mart', 'Април' => 'April',
            'Мај' => 'Maj', 'Јун' => 'Jun', 'Јул' => 'Jul', 'Август' => 'Avgust',
            'Септембар' => 'Septembar', 'Октобар' => 'Oktobar', 'Новембар' => 'Novembar', 'Децембар' => 'Decembar',
        ];
    }
}

if (!function_exists('ekp_rs_translate')) {
    function ekp_rs_translate($html) {
        if (stripos($html, '<html') === false) {
            return $html;
        }
        $parts = preg_split(
            '#(<script\b[^>]*>.*?</script>|<style\b[^>]*>.*?</style>|<!--.*?-->|<[^>]+>)#is',
            $html, -1, PREG_SPLIT_DELIM_CAPTURE
        );
        if ($parts === false) {
            return $html;
        }
        $map = ekp_rs_map();
        foreach ($parts as &$part) {
            if ($part === '') {
                continue;
            }
            if ($part[0] !== '<') {
                $part = strtr($part, $map);
            } elseif (preg_match('#^<meta\b[^>]*(name|property)="(description|og:title|og:description|twitter:title|twitter:description)"#i', $part)) {
                $part = preg_replace_callback('#content="([^"]*)"#i', function ($m) use ($map) {
                    return 'content="' . strtr($m[1], $map) . '"';
                }, $part);
            } elseif (preg_match('#^<(input|textarea)\b#i', $part)) {
                $part = preg_replace_callback('#placeholder="([^"]*)"#i', function ($m) use ($map) {
                    return 'placeholder="' . strtr($m[1], $map) . '"';
                }, $part);
            }
        }
        unset($part);
        return implode('', $parts);
    }
}

add_action('template_redirect', function () {
    if (get_current_blog_id() !== EKP_RS_BLOG_ID || is_admin() || is_feed() || wp_doing_ajax()) {
        return;
    }
    ob_start('ekp_rs_translate');
}, 0);
