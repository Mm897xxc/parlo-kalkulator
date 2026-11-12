<?php
/**
 * archive-store.php
 * @package WordPress
 * @subpackage Froday
 * @since Froday 1.0
 */

/* ============================================================
 * eShopper blok: povezuje stranicu trgovine na eKuponima sa
 * stranicom iste trgovine na eshopper.hr (usporedba cijena).
 * - prikazuje se samo na glavnoj (hrvatskoj) stranici
 * - popis trgovina čita iz eshopper.hr/sitemaps/shops.xml,
 *   sprema u cache na 12 sati
 * - ručno: ACF polje "eshopper_url" na trgovini (taksonomija store)
 *   * upiši punu adresu za ručno spajanje
 *   * upiši "ne" da se blok ne prikazuje za tu trgovinu
 * ============================================================ */

if ( ! function_exists( 'ekp_eshopper_shops' ) ) {
    function ekp_eshopper_shops() {
        $cached = get_transient( 'ekp_eshopper_shops' );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $shops = array();
        $resp  = wp_remote_get( 'https://eshopper.hr/sitemaps/shops.xml', array( 'timeout' => 8 ) );

        if ( ! is_wp_error( $resp ) && 200 === (int) wp_remote_retrieve_response_code( $resp ) ) {
            $body = wp_remote_retrieve_body( $resp );
            if ( preg_match_all( '#<loc>https://eshopper\.hr/trgovina/([a-z0-9-]+)(?:/([a-z0-9-]+))?(?:/[a-z0-9-]+)?</loc>#', $body, $m, PREG_SET_ORDER ) ) {
                foreach ( $m as $row ) {
                    $slug = $row[1];
                    if ( ! isset( $shops[ $slug ] ) ) {
                        $shops[ $slug ] = array();
                    }
                    if ( ! empty( $row[2] ) && ! in_array( $row[2], $shops[ $slug ], true ) ) {
                        $shops[ $slug ][] = $row[2];
                    }
                }
            }
        }

        // Ako dohvat ne uspije, probaj ponovno za sat vremena.
        set_transient( 'ekp_eshopper_shops', $shops, empty( $shops ) ? HOUR_IN_SECONDS : 12 * HOUR_IN_SECONDS );
        return $shops;
    }
}

if ( ! function_exists( 'ekp_eshopper_key' ) ) {
    // "aboutyou-hr" -> "aboutyou", "About You" -> "aboutyou", "Notino.hr" -> "notino"
    function ekp_eshopper_key( $s ) {
        $s = remove_accents( strtolower( trim( (string) $s ) ) );
        $s = preg_replace( '#^https?://#', '', $s );
        $s = preg_replace( '#^(www|hr|en|store)[.-]#', '', $s );
        $s = preg_replace( '#[.-](hr|com|eu|net|shop|de|si|at|it|cz|sk|pl|co-uk|com-hr)$#', '', $s );
        $s = preg_replace( '#[.-](com|co)$#', '', $s ); // npr. "gume.com.hr"
        $s = preg_replace( '#-(kupon|kuponi|kod|kodovi|popust|promo)(-.*)?$#', '', $s );
        return preg_replace( '#[^a-z0-9]#', '', $s );
    }
}

if ( ! function_exists( 'ekp_eshopper_block' ) ) {
    function ekp_eshopper_block( $term ) {
        if ( ! $term || empty( $term->term_id ) ) {
            return '';
        }
        if ( is_multisite() && ! is_main_site() ) {
            return ''; // eShopper je samo za Hrvatsku
        }

        $url  = '';
        $cats = array();

        $manual = function_exists( 'get_field' ) ? trim( (string) get_field( 'eshopper_url', $term->taxonomy . '_' . $term->term_id ) ) : '';
        if ( 'ne' === strtolower( $manual ) ) {
            return '';
        }

        $shops = ekp_eshopper_shops();

        if ( $manual && 0 === strpos( $manual, 'https://eshopper.hr/' ) ) {
            $url = $manual;
            if ( preg_match( '#/trgovina/([a-z0-9-]+)#', $manual, $mm ) && isset( $shops[ $mm[1] ] ) ) {
                $cats = $shops[ $mm[1] ];
            }
        } else {
            $want = array_filter( array_unique( array(
                ekp_eshopper_key( $term->slug ),
                ekp_eshopper_key( $term->name ),
            ) ) );
            foreach ( $shops as $slug => $shop_cats ) {
                if ( in_array( ekp_eshopper_key( $slug ), $want, true ) ) {
                    $url  = 'https://eshopper.hr/trgovina/' . $slug;
                    $cats = $shop_cats;
                    break;
                }
            }
        }

        if ( ! $url ) {
            return '';
        }

        $labels = array(
            'odjeca-i-obuca'      => 'Odjeća i obuća',
            'dom-i-vrt'           => 'Dom i vrt',
            'ljepota-i-zdravlje'  => 'Ljepota i zdravlje',
            'elektronika'         => 'Elektronika',
            'auto-moto'           => 'Auto-moto',
            'djeca-i-igracke'     => 'Djeca i igračke',
            'sport-i-rekreacija'  => 'Sport i rekreacija',
            'kucni-ljubimci'      => 'Kućni ljubimci',
            'hrana-i-pice'        => 'Hrana i piće',
            'hobi-ured-i-skola'   => 'Hobi, ured i škola',
        );

        $store = esc_html( $term->name );
        $base  = untrailingslashit( preg_replace( '#(/trgovina/[a-z0-9-]+).*$#', '$1', $url ) );

        $chips = '';
        $n     = 0;
        foreach ( $cats as $c ) {
            if ( ! isset( $labels[ $c ] ) || $n >= 4 ) {
                continue;
            }
            $chips .= '<a class="ekp-esh-chip" href="' . esc_url( $base . '/' . $c ) . '" target="_blank" rel="noopener">' . esc_html( $labels[ $c ] ) . '</a>';
            $n++;
        }

        ob_start(); ?>
        <style>
            .ekp-esh{margin:20px 0 10px;padding:18px 20px;border:1px solid #d6efe9;border-left:4px solid #31bda4;border-radius:8px;background:#f5fbfa}
            .ekp-esh h4{margin:0 0 6px;font-size:17px;font-weight:700;color:#1d2b36}
            .ekp-esh p{margin:0 0 12px;font-size:14px;line-height:1.5;color:#4a5560}
            .ekp-esh-btn{display:inline-block;padding:9px 16px;border-radius:6px;background:#31bda4;color:#fff !important;font-weight:700;text-decoration:none}
            .ekp-esh-btn:hover{background:#28a28c}
            .ekp-esh-chips{margin-top:12px}
            .ekp-esh-chip{display:inline-block;margin:0 6px 6px 0;padding:4px 10px;border:1px solid #bfe5dd;border-radius:14px;font-size:13px;color:#1f7f6d !important;text-decoration:none;background:#fff}
            .ekp-esh-note{margin-top:8px !important;font-size:12px !important;color:#8a95a0 !important}
        </style>
        <div class="ekp-esh">
            <h4>Prije nego iskoristiš kod: provjeri cijenu</h4>
            <p>Isti proizvod često je jeftiniji u drugoj trgovini. Usporedi cijene i dostavu proizvoda iz trgovine <?php echo $store; ?> s drugim web trgovinama koje dostavljaju u Hrvatsku.</p>
            <a class="ekp-esh-btn" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">Usporedi cijene <?php echo $store; ?> proizvoda na eShopperu →</a>
            <?php if ( $chips ) : ?>
                <div class="ekp-esh-chips"><?php echo $chips; ?></div>
            <?php endif; ?>
            <p class="ekp-esh-note">eShopper je naš besplatni servis za usporedbu cijena.</p>
        </div>
        <?php
        return ob_get_clean();
    }
}
?>

<?php get_header(); ?>

<script type="text/javascript">
    var froday_ajax_object = {
        ajax_url: "<?php echo admin_url( 'admin-ajax.php' ); ?>"
    };
</script>

<main id="mainContent" class="main-content testing">
    <div class="page-container ptb-30">
        <div class="container">
            <div class="row row-rl-10 row-tb-20">
                <div id="desktop-sidebar" class="page-sidebar col-sm-4 col-md-3">
                    <aside class="store-header-area panel t-center">
                        <div class="row">
                            <div class="col-xs-12">
                                <figure class="pt-10 pl-10">
                                    <?php echo froday_store_thumbnail(); ?>
                                </figure>
                            </div>

                            <div class="col-xs-12">
                                <div class="store-about ptb-30 prl-10">
                                    <h3 class="mb-10"><?php echo froday_store_title(); ?></h3>

                                    <div>
                                    <?php echo do_shortcode ('[site_reviews_summary assigned_posts="post_id" hide="bars" schema="true" text="{rating} od {max} zvjezdica (na temelju {num} osvrta)"]'); ?>
                                    <br></br>
                                    </div>
                                    <p class="mb-15"><?php echo froday_store_description(); ?></p>
                                    <div>
                                    <?php echo do_shortcode ('[site_reviews_form assigned_posts="post_id" hide="email,name,title,content"]'); ?>

                                    </div>

                                </div>
                            </div>
                        </div>
                    </aside>
                </div>

                <div class="page-content col-sm-8 col-md-9">

                    <div class="section store-tabs-area">
                        <div class="tabs tabs-v1">
                            <ul class="nav nav-tabs panel mb-10" role="tablist">
                                <li role="presentation" class="active">
                                    <a href="#coupons" aria-controls="coupons" role="tab" data-toggle="tab" aria-expanded="false"><i class="fa fa-star mr-10"></i><?php esc_html_e('Kuponi i akcije','froday'); ?></a>
                                </li>
                            </ul>

                            <div class="tab-content">

                                <div role="tabpanel" class="tab-pane ptb-20 active" id="coupons">
                                    <section class="section coupons-area coupons-area-list">
                                        <div class="row row-masnory row-tb-20">

                                        <?php $terms = get_the_terms( get_the_ID(), 'store');
                                        if($terms){
                                        foreach($terms as $term) {
                                            global $post;
                                            global $product;
                                            $paged    = (get_query_var('paged')) ? get_query_var('paged') : 1;

                                            $args = array(
                                                'post_type' => 'shop_coupon',
                                                'posts_per_page' => 200,
                                                "paged" => $paged,
                                                'tax_query' => array(
                                                    array(
                                                        'taxonomy' => 'store',
                                                        'field' => 'term_id',
                                                        'terms' => $term->term_id,
                                                    )
                                                )
                                            );
                                            $stores = get_posts( $args );
                                            if ( $stores ) {
                                                $count = 0;
                                                foreach ( $stores as $post ) { ?>

                                            <?php
                                                $post_image = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), "full" );
                                                $coupon_amount = get_post_meta( get_the_ID(), 'coupon_amount', true );
                                                $coupon = new WC_Coupon(get_the_ID());
                                                $expiry_date = $coupon->expiry_date;
                                                $formatted_date = date("d-m-Y", strtotime($expiry_date));
                                                $destination_url = get_post_meta( get_the_ID(), 'klb_coupon_destination_url', true );
                                                $mustlogin = get_post_meta( get_the_ID(), 'klb_must_login', true );
                                                $titleid = get_the_title();
                                                $strid = preg_replace('/[^a-zA-Z0-9-_.]/','', $titleid);
                                                $custm_title = get_the_title();
                                                $cou_type = get_field("coupon_type");
                                                $cou_value = get_field("multiple_coupon_codes");
                                                $my_account = esc_url(home_url('/my-account'));


                                                $coupon_code_from_cookie = '';
                                                if(!empty($cou_type) && $mustlogin){
                                                    $cookie_name = 'coupon_' . $post->ID;
                                                    if(isset($_COOKIE[$cookie_name]) && !empty($_COOKIE[$cookie_name])){
                                                        $coupon_code_from_cookie = sanitize_text_field($_COOKIE[$cookie_name]);
                                                    }
                                                }


                                                if($titleid == 'Iskoristi popust'){
                                                    $target="<a class='mwt_".$post->ID."' href=".$destination_url." target='_blank'>";
                                                } else {
                                                    $pUrl=$strid.$post->ID;

                                                    $target="<a class='external_button_trigger' href=".$destination_url." target='_self' data-postid='".$post->ID."' data-mustlogin='".$mustlogin."' data-popup='".$pUrl."'>";
                                                }

                                            ?>

                                            <div class="col-sm-12" id="post-<?php echo esc_attr($post->ID); ?>" style="padding-top:10px; padding-bottom:10px;">
                                                <div class="coupon-single panel t-center t-sm-left">
                                                    <div class="row row-sm-cell row-tb-0 row-rl-0">
                                                        <?php if($post_image[0]){ ?>
                                                            <div class="col-sm-5">
                                                                <?php echo $target;?>
                                                                <div class="text-center pb-20 pb-onmobile-class">
                                                                    <img class="store-logo" src="<?php echo esc_url($post_image[0]); ?>" alt="<?php esc_attr_e('couponimg','froday'); ?>">
                                                                </div>
                                                                </a>
                                                            </div>
                                                        <?php } ?>
                                                        <div class="col-sm-7">
                                                            <div class="panel-body">
                                                                <ul class="deal-meta list-inline mb-0">
                                                                    <?php echo froday_location_name(); ?>
                                                                    <li class="color-muted"><i class="ico lnr lnr-store mr-5"></i><?php echo froday_store_title(); ?></li>
                                                                </ul>
                                                                <?php echo $target;?>
                                                                    <h5 class="deal-title mb-0 mt-5 expiry-class" style="font-weight:700;"><?php the_excerpt(); ?></h5>
                                                                </a>
                                                                <a data-toggle="modal" id="mwt_<?php echo esc_attr($post->ID); ?>" data-target="#<?php echo esc_attr($strid); ?><?php echo esc_attr(get_the_ID()); ?>"></a>
                                                                <?php echo $target;?>

                                                                <?php if($expiry_date){ ?>
                                                                    <p class="mb-0 color-muted mb-0 font-12 expiry-class"><i class="lnr lnr-clock mr-10"></i><?php esc_html_e('ISTJEČE','froday'); ?> <?php echo esc_html($formatted_date); ?></p>
                                                                <?php } else { ?>
                                                                    <p class="mb-0 color-muted mb-0 font-12 expiry-class"><i
                                                                    class="lnr lnr-clock mr-10"></i><?php esc_html_e('ISTEK NEODREĐEN','froday'); ?></p>
                                                                <?php } ?>
                                                                </a>
                                                                <div class="showcode">


                                                                <?php if($titleid == 'Iskoristi popust'){ ?> <a href="<?php echo esc_url($destination_url); ?>" target="_blank">
        <button class="show-code btn btn-sm btn-block"
                data-toggle="modal"
                data-target="#<?php echo esc_attr($strid); ?><?php echo esc_attr(get_the_ID()); ?>">
            <?php esc_html_e('Pogledaj akciju ➝','froday'); ?>
        </button>
    </a>
                                                                    <?php } else { ?>
                                                                    <?php
                                                                    $izabrani_tip = get_field('tip_ponude');

                                                                    $dodatna_klasa = '';
                                                                    if($izabrani_tip == 'kupon') {
                                                                        $dodatna_klasa = 'kupon-zeleni';
                                                                    } elseif($izabrani_tip == 'ekskluzivni kupon') {
                                                                        $dodatna_klasa = 'kupon-oker';
                                                                    }
                                                                    ?>
                                                                    <a class="external_button_trigger" href="<?php echo esc_url($destination_url); ?>" target="_self" data-postid="<?php echo esc_attr($post->ID); ?>" data-mustlogin="<?php echo esc_attr($mustlogin); ?>" data-popup="<?php echo esc_attr($strid); ?><?php echo esc_attr(get_the_ID()); ?>"><button class="show-code btn btn-sm btn-block coupon-trigger-btn <?php echo esc_attr($dodatna_klasa); ?>" data-popup-id="<?php echo esc_attr($strid); ?><?php echo esc_attr(get_the_ID()); ?>"><?php esc_html_e('Prikaži kod','froday'); ?></button></a>
                                                                <?php } ?>

                                                                    <div class="coupon-hide <?php echo $dodatna_klasa; ?>" data-postid="<?php echo esc_attr($post->ID); ?>" data-type="<?php echo esc_attr($cou_type); ?>" data-mustlogin="<?php echo esc_attr($mustlogin); ?>" data-coupon-value="<?php echo esc_attr($cou_value); ?>" data-title-id="<?php echo esc_attr($titleid); ?>">
                                                                        <?php echo ($coupon_code_from_cookie) ? esc_html($coupon_code_from_cookie) : $custm_title; ?>
                                                                    </div>


                                                                </div>
                                                            </div>
                                                        </div>
                                                        </div>
                                                    </div>


                                                <script>
                                                function frodayCopy_<?php echo esc_html($count); ?>() {
                                                  var copyText = document.getElementById("<?php echo esc_attr($strid); ?><?php echo esc_html($count); ?>");
                                                  copyText.select();
                                                  document.execCommand("copy");
                                                  copyText.classList.add("copied");
                                                  <?php if($destination_url){ ?>
                                                //	window.open('<?php echo esc_url($destination_url); ?>','_blank');
                                                  <?php } ?>
                                                }
                                                </script>

                                                <div class="modal fade get-coupon-area" tabindex="-1" role="dialog" id="<?php echo esc_attr($strid); ?><?php echo esc_attr(get_the_ID()); ?>">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content panel">
                                                            <div class="modal-body">
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span>
                                                                </button>
                                                                <div class="row row-v-10">
                                                                    <div class="col-md-10 col-md-offset-1">
                                                                        <?php echo froday_store_thumbnail(); ?>
                                                                        <h3 class="mb-20"><?php the_excerpt(); ?></h3>
                                                                        <?php echo get_post_field('post_content', $post->ID); ?>
                                                                    </div>
                                                                    <div class="col-md-10 col-md-offset-1">
                                                                        <h6 class="color-mid t-uppercase"><?php esc_html_e('','froday'); ?></h6>
                                                                        <div class="copy-coupon-wrap">
                                                                             <?php

                                                                            $modal_display_code = ($coupon_code_from_cookie) ? $coupon_code_from_cookie : $custm_title;

                                                                            echo '<input type="text" value="'.esc_attr($modal_display_code).'" id="'.$strid . esc_html($count).'" class="coupon-code code-'.$post->ID.'">
                                                                            <p class="clipboard" style="display:none">'.esc_html__('Kod je kopiran: ','froday').'<a href="'. $destination_url .'" target="_blank" style="color: #25c76f; text-decoration: underline;">POSJETI WEBSHOP</a></p>
                                                                            <button onclick="frodayCopy_'.$count.'()">'.esc_html__('Copy Coupon','froday').'</button>';
                                                                            ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>


                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php $count++; ?>
                                            <?php } ?>
                                            <div class="col-md-12">
                                            <?php get_template_part( 'post-format/pagination' ); ?>
                                            </div>
                                            <?php wp_reset_postdata(); ?>
                                            <?php } } } ?>
                                        </div>
                                    </section>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php echo ekp_eshopper_block( get_queried_object() ); ?>

                    <div id="mobile-sidebar" class="page-sidebar col-sm-4 col-md-3">
                        <aside class="store-header-area panel t-center">
                            <div class="row">
                                <div class="col-xs-12">
                                    <figure class="pt-10 pl-10">
                                        <?php echo froday_store_thumbnail(); ?>
                                    </figure>
                                </div>

                                <div class="col-xs-12">
                                    <div class="store-about ptb-30 prl-10">
                                        <h3 class="mb-10"><?php echo froday_store_title(); ?></h3>

                                        <div>
                                        <?php echo do_shortcode ('[site_reviews_summary assigned_posts="post_id" hide="bars" text="{rating} od {max} zvjezdica (na temelju {num} osvrta)"]'); ?>
                                        <br></br>
                                        </div>
                                        <p class="mb-15"><?php echo froday_store_description(); ?></p>
                                        <div>
                                        <?php echo do_shortcode ('[site_reviews_form assigned_posts="post_id" hide="email,name,title,content"]'); ?>

                                        </div>

                                    </div>
                                </div>
                            </div>
                        </aside>
                    </div>




                </div>


            </div>
        </div>
    </div>

<?php
$term = get_queried_object();
$faq_store = get_field('faq_store', $term->taxonomy . '_' . $term->term_id);
if ( !empty($faq_store) ) : ?>
    <section class="section store-faq-area">
        <div class="container">
            <div class="panel store-faq-content">
                <?php echo apply_filters('the_content', $faq_store); ?>
            </div>
        </div>
    </section>
<?php endif; ?>



    </main>

<script type="text/javascript">

    var getUrlParameter = function getUrlParameter(sParam) {
                var sPageURL = window.location.search.substring(1),
                    sURLVariables = sPageURL.split('&'),
                    sParameterName,
                    i;

                for (i = 0; i < sURLVariables.length; i++) {
                    sParameterName = sURLVariables[i].split('=');

                    if (sParameterName[0] === sParam) {
                        return typeof sParameterName[1] === undefined ? true : decodeURIComponent(sParameterName[1]);
                    }
                }
                return false;
    };
    function isFacebookApp() {
        var ua = navigator.userAgent || navigator.vendor || window.opera;
        return (ua.indexOf("FBAN") > -1) || (ua.indexOf("FBAV") > -1);
    }


    jQuery(document).ready(function(){
        jQuery('[class*=mwt_]').click(function(){
            var arr = this.className;
            jQuery("#"+arr).trigger('click');
        });


        jQuery('a.external_button_trigger').click(function(e){
            var $this = jQuery(this);
            var popUpCode = $this.data('popup');
            var postID = $this.data('postid');
            var mustLogin = $this.data('mustlogin');
            var destinationUrl = $this.attr('href');


            if (mustLogin == 1) {
                e.preventDefault();


                $this.prop('disabled', true).addClass('loading');


                jQuery.ajax({
                    url: froday_ajax_object.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'froday_get_coupon_code',
                        post_id: postID
                    },
                    success: function(response) {
                        if (response.success) {
                            var assignedCode = response.data.code;


                            jQuery('.coupon-hide[data-postid="' + postID + '"]').text(assignedCode);
                            jQuery('.coupon-code.code-' + postID).val(assignedCode);


                            if(isFacebookApp()){
                                jQuery("a[data-target='#"+popUpCode+"']").trigger('click');
                            }else{

                                jQuery("a[data-target='#"+popUpCode+"']").trigger('click');
                            }
                        } else {
                            console.error('Greška pri dohvaćanju kupona:', response.data.message);
                            alert('Došlo je do greške. Pokušajte ponovo.');
                        }
                    },
                    error: function() {
                        console.error('AJAX greška.');
                        alert('Došlo je do greške u komunikaciji sa serverom.');
                    },
                    complete: function() {

                        $this.prop('disabled', false).removeClass('loading');
                    }
                });

            } else {

                if(isFacebookApp()){
                    jQuery("a[data-target='#"+popUpCode+"']").trigger('click');
                    return false;
                }else{
                    var href  = document.location.protocol +'//'+ window.location.hostname + window.location.pathname +'?u='+popUpCode;
                    window.open(href, '_blank');
                }
            }
        });

        var popUp_code = getUrlParameter('u');
        if(popUp_code){
            console.log(popUp_code);
            jQuery("a[data-target='#"+popUp_code+"']").trigger('click');
        }

    });//ready

</script>

<?php get_footer(); ?>
