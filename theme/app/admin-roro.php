<?php
/**
 * RORO 管理画面
 *
 * Provides a simple administration page within the WordPress dashboard
 * for viewing customer and pet data.  This page is only accessible
 * to users with the `manage_roro` capability.  It displays a list
 * of customers and a list of pets.  At this stage the lists are
 * read‑only.  Future enhancements may include inline editing and
 * search/filter capabilities.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the RORO administration page.  Lists customers and pets
 * with basic details.  Access is restricted via capability check.
 */
function roro_render_admin_page() {
    if ( ! current_user_can( 'manage_roro' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.', 'roro' ) );
    }
    global $wpdb;
    // Determine search and pagination parameters from the query string.  Each
    // list (customers / pets) has its own search term and page number.
    $csearch = isset( $_GET['csearch'] ) ? sanitize_text_field( wp_unslash( $_GET['csearch'] ) ) : '';
    $psearch = isset( $_GET['psearch'] ) ? sanitize_text_field( wp_unslash( $_GET['psearch'] ) ) : '';
    // Favorites search and type parameters.  "ftype" may be 'event', 'spot' or 'all' (default).
    $fsearch = isset( $_GET['fsearch'] ) ? sanitize_text_field( wp_unslash( $_GET['fsearch'] ) ) : '';
    $ftype  = isset( $_GET['ftype'] ) ? sanitize_text_field( wp_unslash( $_GET['ftype'] ) ) : 'all';
    $cpage   = isset( $_GET['cpage'] ) ? max( 1, intval( $_GET['cpage'] ) ) : 1;
    $ppage   = isset( $_GET['ppage'] ) ? max( 1, intval( $_GET['ppage'] ) ) : 1;
    $fpage   = isset( $_GET['fpage'] ) ? max( 1, intval( $_GET['fpage'] ) ) : 1;
    $per_page = 20;
    // Build query for customers with optional search filtering
    $cust_table = $wpdb->prefix . 'RORO_CUSTOMER';
    $c_where = '1=1';
    if ( $csearch !== '' ) {
        // Prepare a wildcard search string for ID (cast to char), prefecture or city
        $like = '%' . $wpdb->esc_like( $csearch ) . '%';
        // Use prepare to safely insert variables; note that prepare will
        // quote the values as needed.  Concatenate to the where clause.
        $c_where .= $wpdb->prepare( ' AND (CAST(customer_id AS CHAR) LIKE %s OR prefecture LIKE %s OR city LIKE %s)', $like, $like, $like );
    }
    // Get total count for pagination
    $c_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$cust_table} WHERE {$c_where}" );
    $c_total_pages = max( 1, (int) ceil( $c_total / $per_page ) );
    $c_offset = ( $cpage - 1 ) * $per_page;
    // Fetch paged customer records
    $c_sql = $wpdb->prepare( "SELECT customer_id, prefecture, city FROM {$cust_table} WHERE {$c_where} ORDER BY customer_id ASC LIMIT %d OFFSET %d", $per_page, $c_offset );
    $customers = $wpdb->get_results( $c_sql );
    // Build query for pets with optional search filtering
    $pet_table = $wpdb->prefix . 'RORO_PET';
    $p_where = '1=1';
    if ( $psearch !== '' ) {
        $like = '%' . $wpdb->esc_like( $psearch ) . '%';
        $p_where .= $wpdb->prepare( ' AND (CAST(pet_id AS CHAR) LIKE %s OR CAST(customer_id AS CHAR) LIKE %s OR species LIKE %s OR pet_name LIKE %s)', $like, $like, $like, $like );
    }
    $p_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$pet_table} WHERE {$p_where}" );
    $p_total_pages = max( 1, (int) ceil( $p_total / $per_page ) );
    $p_offset = ( $ppage - 1 ) * $per_page;
    $p_sql = $wpdb->prepare( "SELECT pet_id, customer_id, species, pet_name FROM {$pet_table} WHERE {$p_where} ORDER BY pet_id ASC LIMIT %d OFFSET %d", $per_page, $p_offset );
    $pets = $wpdb->get_results( $p_sql );

    /**
     * Favourites retrieval using model.  This utilises Roro_FavoriteModel::search_paged
     * to perform the filtering and pagination logic.  Filtering is based on
     * the search term and target type.  The result includes total count
     * and row objects with contextual fields.
     */
    // Ensure the model file is loaded
    require_once get_template_directory() . '/refactor/app/models/Roro_FavoriteModel.php';
    $fav_model = new Roro_FavoriteModel();
    $fav_result = $fav_model->search_paged( array(
        'page' => $fpage,
        'per'  => $per_page,
        'q'    => $fsearch,
        'type' => ( $ftype === 'all' ? '' : $ftype ),
    ) );
    $f_total = $fav_result['total'];
    // Compute total pages for pagination
    $f_total_pages = max( 1, (int) ceil( $f_total / $per_page ) );
    // Extract result rows
    $favorites = $fav_result['rows'];

    echo '<div class="wrap">';
    echo '<h1>RORO 管理</h1>';
    // Display success messages if updates were processed
    if ( isset( $_GET['updated'] ) ) {
        $updated = sanitize_text_field( wp_unslash( $_GET['updated'] ) );
        if ( $updated === 'customer' ) {
            echo '<div class="notice notice-success"><p>顧客情報を更新しました。</p></div>';
        } elseif ( $updated === 'pet' ) {
            echo '<div class="notice notice-success"><p>ペット情報を更新しました。</p></div>';
        }
    }
    // Display deletion notice for favorites
    if ( isset( $_GET['deleted'] ) ) {
        $deleted = sanitize_text_field( wp_unslash( $_GET['deleted'] ) );
        if ( $deleted === 'favorite' ) {
            echo '<div class="notice notice-success"><p>お気に入りを削除しました。</p></div>';
        }
    }
    // Customers list with search, CSV export, pagination and inline edit
    echo '<h2>顧客一覧</h2>';
    // Search form for customers
    echo '<form method="get" style="margin-bottom:1em;">';
    echo '<input type="hidden" name="page" value="roro-admin" />';
    // Preserve pets search/page parameters when submitting customer search
    echo '<input type="hidden" name="ppage" value="' . esc_attr( $ppage ) . '" />';
    echo '<input type="hidden" name="psearch" value="' . esc_attr( $psearch ) . '" />';
    echo '<input type="text" name="csearch" value="' . esc_attr( $csearch ) . '" class="regular-text" placeholder="ID・都道府県・市区町村で検索" /> ';
    echo '<button type="submit" class="button">検索</button>';
    echo '</form>';
    // CSV export button for customers
    echo '<a href="' . esc_url( admin_url( 'admin-post.php?action=roro_export_customers' ) ) . '" class="button" style="margin-left:0.5em;">CSVエクスポート</a>';
    // Table header
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>都道府県</th><th>市区町村</th><th>操作</th></tr></thead><tbody>';
    if ( $customers ) {
        foreach ( $customers as $cust ) {
            echo '<tr>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            // Nonce field for CSRF protection
            echo wp_nonce_field( 'roro_update_customer', '_wpnonce_roro_update_customer', true, false );
            echo '<input type="hidden" name="action" value="roro_update_customer" />';
            echo '<input type="hidden" name="customer_id" value="' . esc_attr( $cust->customer_id ) . '" />';
            echo '<td>' . esc_html( $cust->customer_id ) . '</td>';
            // Prefecture select
            echo '<td><select name="prefecture">';
            foreach ( roro_get_japanese_prefectures() as $pref ) {
                $selected = selected( $cust->prefecture, $pref, false );
                echo '<option value="' . esc_attr( $pref ) . '" ' . $selected . '>' . esc_html( $pref ) . '</option>';
            }
            echo '</select></td>';
            echo '<td><input type="text" name="city" value="' . esc_attr( $cust->city ) . '" class="regular-text" /></td>';
            echo '<td><button type="submit" class="button button-primary">保存</button></td>';
            echo '</form>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="4">顧客データがありません。</td></tr>';
    }
    echo '</tbody></table>';
    // Customer pagination controls
    echo '<div class="tablenav"><div class="tablenav-pages">';
    // Display the range of records
    $c_start = $c_total > 0 ? ( ( $cpage - 1 ) * $per_page + 1 ) : 0;
    $c_end   = min( $c_total, $cpage * $per_page );
    echo '<span class="displaying-num">' . $c_total . '件中 ' . $c_start . '–' . $c_end . '件表示</span> ';
    if ( $cpage > 1 ) {
        $prev_c = $cpage - 1;
        $prev_url = add_query_arg( array(
            'page'    => 'roro-admin',
            'cpage'   => $prev_c,
            'csearch' => $csearch,
            'ppage'   => $ppage,
            'psearch' => $psearch,
        ), admin_url( 'admin.php' ) );
        echo '<a class="prev-page button" href="' . esc_url( $prev_url ) . '">« 前へ</a> ';
    }
    if ( $cpage < $c_total_pages ) {
        $next_c = $cpage + 1;
        $next_url = add_query_arg( array(
            'page'    => 'roro-admin',
            'cpage'   => $next_c,
            'csearch' => $csearch,
            'ppage'   => $ppage,
            'psearch' => $psearch,
        ), admin_url( 'admin.php' ) );
        echo '<a class="next-page button" href="' . esc_url( $next_url ) . '">次へ »</a>';
    }
    echo '</div></div>';
    // Pets list with search, pagination and inline edit
    echo '<h2>ペット一覧</h2>';
    // Search form for pets
    echo '<form method="get" style="margin-bottom:1em;">';
    echo '<input type="hidden" name="page" value="roro-admin" />';
    // Preserve customer search/page parameters when submitting pet search
    echo '<input type="hidden" name="cpage" value="' . esc_attr( $cpage ) . '" />';
    echo '<input type="hidden" name="csearch" value="' . esc_attr( $csearch ) . '" />';
    echo '<input type="text" name="psearch" value="' . esc_attr( $psearch ) . '" class="regular-text" placeholder="ID・顧客ID・種類・名前で検索" /> ';
    echo '<button type="submit" class="button">検索</button>';
    echo '</form>';
    // CSV export button for pets
    echo '<a href="' . esc_url( admin_url( 'admin-post.php?action=roro_export_pets' ) ) . '" class="button" style="margin-left:0.5em;">CSVエクスポート</a>';
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>ペットID</th><th>顧客ID</th><th>種類</th><th>名前</th><th>操作</th></tr></thead><tbody>';
    if ( $pets ) {
        foreach ( $pets as $pet ) {
            echo '<tr>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            // CSRF nonce for pet update
            echo wp_nonce_field( 'roro_update_pet', '_wpnonce_roro_update_pet', true, false );
            echo '<input type="hidden" name="action" value="roro_update_pet" />';
            echo '<input type="hidden" name="pet_id" value="' . esc_attr( $pet->pet_id ) . '" />';
            echo '<input type="hidden" name="customer_id" value="' . esc_attr( $pet->customer_id ) . '" />';
            echo '<td>' . esc_html( $pet->pet_id ) . '</td>';
            echo '<td>' . esc_html( $pet->customer_id ) . '</td>';
            // Species select (use lower case values; DB stores upper case)
            $species = strtolower( $pet->species );
            echo '<td><select name="species">';
            echo '<option value="dog"' . selected( $species, 'dog', false ) . '>犬</option>';
            echo '<option value="cat"' . selected( $species, 'cat', false ) . '>猫</option>';
            echo '<option value="other"' . selected( $species, 'other', false ) . '>その他</option>';
            echo '</select></td>';
            echo '<td><input type="text" name="pet_name" value="' . esc_attr( $pet->pet_name ) . '" class="regular-text" /></td>';
            echo '<td><button type="submit" class="button button-primary">保存</button></td>';
            echo '</form>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="5">ペットデータがありません。</td></tr>';
    }
    echo '</tbody></table>';
    // Pets pagination controls
    echo '<div class="tablenav"><div class="tablenav-pages">';
    $p_start = $p_total > 0 ? ( ( $ppage - 1 ) * $per_page + 1 ) : 0;
    $p_end   = min( $p_total, $ppage * $per_page );
    echo '<span class="displaying-num">' . $p_total . '件中 ' . $p_start . '–' . $p_end . '件表示</span> ';
    if ( $ppage > 1 ) {
        $prev_p = $ppage - 1;
        $prev_url = add_query_arg( array(
            'page'    => 'roro-admin',
            'ppage'   => $prev_p,
            'psearch' => $psearch,
            'cpage'   => $cpage,
            'csearch' => $csearch,
        ), admin_url( 'admin.php' ) );
        echo '<a class="prev-page button" href="' . esc_url( $prev_url ) . '">« 前へ</a> ';
    }
    if ( $ppage < $p_total_pages ) {
        $next_p = $ppage + 1;
        $next_url = add_query_arg( array(
            'page'    => 'roro-admin',
            'ppage'   => $next_p,
            'psearch' => $psearch,
            'cpage'   => $cpage,
            'csearch' => $csearch,
        ), admin_url( 'admin.php' ) );
        echo '<a class="next-page button" href="' . esc_url( $next_url ) . '">次へ »</a>';
    }
    echo '</div></div>';
    // Favorites list with search, pagination and delete
    echo '<h2>お気に入り一覧</h2>';
    // Search form for favorites
    echo '<form method="get" style="margin-bottom:1em;">';
    echo '<input type="hidden" name="page" value="roro-admin" />';
    // Preserve customers and pets search/page parameters when submitting favorites search
    echo '<input type="hidden" name="cpage" value="' . esc_attr( $cpage ) . '" />';
    echo '<input type="hidden" name="csearch" value="' . esc_attr( $csearch ) . '" />';
    echo '<input type="hidden" name="ppage" value="' . esc_attr( $ppage ) . '" />';
    echo '<input type="hidden" name="psearch" value="' . esc_attr( $psearch ) . '" />';
    echo '<input type="text" name="fsearch" value="' . esc_attr( $fsearch ) . '" class="regular-text" placeholder="ID・ユーザーID・対象名などで検索" /> ';
    echo '<select name="ftype">';
    echo '<option value="all"' . selected( $ftype, 'all', false ) . '>すべて</option>';
    echo '<option value="event"' . selected( $ftype, 'event', false ) . '>イベント</option>';
    echo '<option value="spot"' . selected( $ftype, 'spot', false ) . '>スポット</option>';
    echo '</select> ';
    echo '<button type="submit" class="button">検索</button>';
    echo '</form>';
    // CSV export button for favorites
    echo '<a href="' . esc_url( admin_url( 'admin-post.php?action=roro_export_favorites' ) ) . '" class="button" style="margin-left:0.5em;">CSVエクスポート</a>';
    // Favorites table
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>ユーザーID</th><th>種別</th><th>対象名</th><th>所在地</th><th>登録日時</th><th>操作</th></tr></thead><tbody>';
    if ( $favorites ) {
        foreach ( $favorites as $fav ) {
            echo '<tr>';
            echo '<td>' . esc_html( $fav->id ) . '</td>';
            echo '<td>' . esc_html( $fav->user_id ) . '</td>';
            // Display type label (Japanese)
            $type_label = ( $fav->target_type === 'event' ) ? 'イベント' : 'スポット';
            echo '<td>' . esc_html( $type_label ) . '</td>';
            echo '<td>' . esc_html( $fav->target_name ? $fav->target_name : '—' ) . '</td>';
            // Location: prefecture & city if available
            $location = '';
            if ( $fav->prefecture ) {
                $location .= $fav->prefecture;
            }
            if ( $fav->city ) {
                $location .= ( $location ? ' ' : '' ) . $fav->city;
            }
            if ( $location === '' ) {
                $location = '—';
            }
            echo '<td>' . esc_html( $location ) . '</td>';
            // Created at: format date/time
            $created = $fav->created_at ? date_i18n( 'Y-m-d H:i', strtotime( $fav->created_at ) ) : '—';
            echo '<td>' . esc_html( $created ) . '</td>';
            // Delete action with nonce
            echo '<td>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            echo '<input type="hidden" name="action" value="roro_delete_favorite" />';
            echo '<input type="hidden" name="id" value="' . esc_attr( $fav->id ) . '" />';
            // Nonce field for favorite deletion
            echo wp_nonce_field( 'roro_delete_favorite', '_wpnonce_roro_delete_favorite', true, false );
            // Preserve query parameters after deletion
            echo '<input type="hidden" name="_wp_http_referer" value="' . esc_attr( $_SERVER['REQUEST_URI'] ) . '" />';
            submit_button( '削除', 'delete', '', false, array( 'onclick' => "return confirm('削除しますか？');" ) );
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="7">お気に入りデータがありません。</td></tr>';
    }
    echo '</tbody></table>';
    // Favorites pagination controls
    echo '<div class="tablenav"><div class="tablenav-pages">';
    $f_start = $f_total > 0 ? ( ( $fpage - 1 ) * $per_page + 1 ) : 0;
    $f_end   = min( $f_total, $fpage * $per_page );
    echo '<span class="displaying-num">' . $f_total . '件中 ' . $f_start . '–' . $f_end . '件表示</span> ';
    if ( $fpage > 1 ) {
        $prev_f = $fpage - 1;
        $prev_url = add_query_arg( array(
            'page'    => 'roro-admin',
            'fpage'   => $prev_f,
            'fsearch' => $fsearch,
            'ftype'   => $ftype,
            'cpage'   => $cpage,
            'csearch' => $csearch,
            'ppage'   => $ppage,
            'psearch' => $psearch,
        ), admin_url( 'admin.php' ) );
        echo '<a class="prev-page button" href="' . esc_url( $prev_url ) . '">« 前へ</a> ';
    }
    if ( $fpage < $f_total_pages ) {
        $next_f = $fpage + 1;
        $next_url = add_query_arg( array(
            'page'    => 'roro-admin',
            'fpage'   => $next_f,
            'fsearch' => $fsearch,
            'ftype'   => $ftype,
            'cpage'   => $cpage,
            'csearch' => $csearch,
            'ppage'   => $ppage,
            'psearch' => $psearch,
        ), admin_url( 'admin.php' ) );
        echo '<a class="next-page button" href="' . esc_url( $next_url ) . '">次へ »</a>';
    }
    echo '</div></div>';

    echo '</div>';
}