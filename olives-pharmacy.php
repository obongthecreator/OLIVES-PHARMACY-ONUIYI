<?php
/**
 * Plugin Name: Olives Pharmacy - Inventory & Sales System
 * Description: Complete inventory, sales, financial and analytics system for Olives Pharmacy.
 * Version: 1.0
 * Author: Olives Pharmacy
 * License: GPL-2.0+
 * Text Domain: olives-pharmacy
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

if ( ! class_exists( 'Olives_Pharmacy_Plugin' ) ) {
class Olives_Pharmacy_Plugin {
const VERSION    = '1.0';
const DB_VERSION = '1.0';
		const NONCE      = 'olives_nonce';
		// Accept small floating-point arithmetic variance (0.5 Naira) when validating split payments.
		const PAYMENT_TOLERANCE = 0.5;

private static $instance = null;

private $pages = array(
'home'      => array(
'title'     => 'Olives — Home',
'slug'      => 'olives',
'shortcode' => '[olives_home]',
),
'dashboard' => array(
'title'     => 'Olives — Dashboard',
'slug'      => 'olives-dashboard',
'shortcode' => '[olives_dashboard]',
),
'stock'     => array(
'title'     => 'Olives — Stock',
'slug'      => 'olives-stock',
'shortcode' => '[olives_stock]',
),
'sales'     => array(
'title'     => 'Olives — Sales',
'slug'      => 'olives-sales',
'shortcode' => '[olives_sales]',
),
'financial' => array(
'title'     => 'Olives — Financial',
'slug'      => 'olives-financial',
'shortcode' => '[olives_financial]',
),
'analytics' => array(
'title'     => 'Olives — Analytics',
'slug'      => 'olives-analytics',
'shortcode' => '[olives_analytics]',
),
);

public static function instance() {
if ( null === self::$instance ) {
self::$instance = new self();
}
return self::$instance;
}

private function __construct() {
add_action( 'init', array( $this, 'register_shortcodes' ) );
add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_assets' ) );
add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

add_action( 'wp_ajax_olives_products_list', array( $this, 'ajax_products_list' ) );
add_action( 'wp_ajax_olives_products_save', array( $this, 'ajax_products_save' ) );
add_action( 'wp_ajax_olives_products_delete', array( $this, 'ajax_products_delete' ) );
add_action( 'wp_ajax_olives_sales_create', array( $this, 'ajax_sales_create' ) );
add_action( 'wp_ajax_olives_sales_list', array( $this, 'ajax_sales_list' ) );
add_action( 'wp_ajax_olives_sales_get', array( $this, 'ajax_sales_get' ) );
add_action( 'wp_ajax_olives_stock_history_list', array( $this, 'ajax_stock_history_list' ) );
add_action( 'wp_ajax_olives_dashboard_stats', array( $this, 'ajax_dashboard_stats' ) );
add_action( 'wp_ajax_olives_financial_summary', array( $this, 'ajax_financial_summary' ) );
add_action( 'wp_ajax_olives_analytics_data', array( $this, 'ajax_analytics_data' ) );
add_action( 'wp_ajax_olives_activity_log', array( $this, 'ajax_activity_log' ) );
}

public static function activate() {
global $wpdb;

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

$charset_collate = $wpdb->get_charset_collate();
$products        = $wpdb->prefix . 'olives_products';
$sales           = $wpdb->prefix . 'olives_sales';
$sale_items      = $wpdb->prefix . 'olives_sale_items';
$stock_history   = $wpdb->prefix . 'olives_stock_history';

$sql = array();
$sql[] = "CREATE TABLE {$products} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
name VARCHAR(191) NOT NULL,
nafdac_number VARCHAR(20) NULL,
batch_number VARCHAR(50) NULL,
expiry_date DATE NULL,
quantity INT NOT NULL DEFAULT 0,
cost_price DECIMAL(12,2) NOT NULL DEFAULT 0,
selling_price DECIMAL(12,2) NOT NULL DEFAULT 0,
reorder_level INT NOT NULL DEFAULT 5,
created_at DATETIME NOT NULL,
updated_at DATETIME NOT NULL,
PRIMARY KEY (id),
KEY name (name),
KEY nafdac_number (nafdac_number),
KEY expiry_date (expiry_date)
) {$charset_collate};";

$sql[] = "CREATE TABLE {$sales} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
receipt_no VARCHAR(30) NOT NULL,
total_amount DECIMAL(12,2) NOT NULL,
payment_method VARCHAR(40) NOT NULL,
cash_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
card_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
transfer_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
staff_id BIGINT UNSIGNED NOT NULL,
staff_name VARCHAR(191) NOT NULL,
created_at DATETIME NOT NULL,
PRIMARY KEY (id),
UNIQUE KEY receipt_no (receipt_no),
KEY created_at (created_at)
) {$charset_collate};";

$sql[] = "CREATE TABLE {$sale_items} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
sale_id BIGINT UNSIGNED NOT NULL,
product_id BIGINT UNSIGNED NOT NULL,
product_name VARCHAR(191) NOT NULL,
unit_price DECIMAL(12,2) NOT NULL,
quantity INT NOT NULL,
line_total DECIMAL(12,2) NOT NULL,
PRIMARY KEY (id),
KEY sale_id (sale_id),
KEY product_id (product_id)
) {$charset_collate};";

$sql[] = "CREATE TABLE {$stock_history} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
product_id BIGINT UNSIGNED NOT NULL,
product_name VARCHAR(191) NOT NULL,
change_type VARCHAR(30) NOT NULL,
quantity_change INT NOT NULL,
quantity_after INT NOT NULL,
note TEXT NULL,
staff_id BIGINT UNSIGNED NULL,
created_at DATETIME NOT NULL,
PRIMARY KEY (id),
KEY product_id (product_id),
KEY created_at (created_at)
) {$charset_collate};";

foreach ( $sql as $query ) {
dbDelta( $query );
}

$instance = self::instance();
$instance->create_plugin_pages();
$instance->seed_demo_products();

update_option( 'olives_db_version', self::DB_VERSION );
flush_rewrite_rules();
}

public static function deactivate() {
flush_rewrite_rules();
}

public static function uninstall() {
global $wpdb;
$tables = array(
$wpdb->prefix . 'olives_products',
$wpdb->prefix . 'olives_sales',
$wpdb->prefix . 'olives_sale_items',
$wpdb->prefix . 'olives_stock_history',
);
foreach ( $tables as $table ) {
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

$pages = get_option( 'olives_pages', array() );
if ( is_array( $pages ) ) {
foreach ( $pages as $id ) {
$id = absint( $id );
if ( $id > 0 ) {
wp_delete_post( $id, true );
}
}
}

delete_option( 'olives_pages' );
delete_option( 'olives_db_version' );
}

private function create_plugin_pages() {
$created = get_option( 'olives_pages', array() );
if ( ! is_array( $created ) ) {
$created = array();
}

foreach ( $this->pages as $key => $page ) {
$existing = get_page_by_path( $page['slug'] );
if ( $existing instanceof WP_Post ) {
$created[ $key ] = $existing->ID;
continue;
}

$page_id = wp_insert_post(
array(
'post_title'   => $page['title'],
'post_name'    => $page['slug'],
'post_status'  => 'publish',
'post_type'    => 'page',
'post_content' => $page['shortcode'],
)
);

if ( ! is_wp_error( $page_id ) ) {
$created[ $key ] = (int) $page_id;
}
}

update_option( 'olives_pages', $created );
}

private function seed_demo_products() {
global $wpdb;
$table = $wpdb->prefix . 'olives_products';
$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
if ( $count > 0 ) {
return;
}

$now  = current_time( 'mysql' );
$tz   = new DateTimeZone( 'Africa/Lagos' );
$demo = array(
array( 'Paracetamol 500mg', 'A4-1234', 'B-001', ( new DateTime( '+1 year', $tz ) )->format( 'Y-m-d' ), 120, 500, 800, 20 ),
array( 'Amoxicillin 250mg', 'A4-2234', 'B-002', ( new DateTime( '+8 months', $tz ) )->format( 'Y-m-d' ), 80, 1200, 1800, 15 ),
array( 'Vitamin C 1000mg', 'A4-3234', 'B-003', ( new DateTime( '+10 months', $tz ) )->format( 'Y-m-d' ), 95, 2000, 3000, 20 ),
array( 'ORS Sachets', 'A4-4234', 'B-004', ( new DateTime( '+6 months', $tz ) )->format( 'Y-m-d' ), 40, 300, 500, 10 ),
array( 'Antacid Syrup', 'A4-5234', 'B-005', ( new DateTime( '+5 months', $tz ) )->format( 'Y-m-d' ), 25, 1800, 2500, 8 ),
);

foreach ( $demo as $d ) {
$wpdb->insert(
$table,
array(
'name'          => $d[0],
'nafdac_number' => $d[1],
'batch_number'  => $d[2],
'expiry_date'   => $d[3],
'quantity'      => $d[4],
'cost_price'    => $d[5],
'selling_price' => $d[6],
'reorder_level' => $d[7],
'created_at'    => $now,
'updated_at'    => $now,
)
);
}
}

public function register_shortcodes() {
add_shortcode( 'olives_home', array( $this, 'shortcode_home' ) );
add_shortcode( 'olives_dashboard', array( $this, 'shortcode_dashboard' ) );
add_shortcode( 'olives_stock', array( $this, 'shortcode_stock' ) );
add_shortcode( 'olives_sales', array( $this, 'shortcode_sales' ) );
add_shortcode( 'olives_financial', array( $this, 'shortcode_financial' ) );
add_shortcode( 'olives_analytics', array( $this, 'shortcode_analytics' ) );
}

private function should_load_front_assets() {
if ( is_admin() ) {
return false;
}
if ( ! is_singular() ) {
return false;
}
global $post;
if ( ! $post instanceof WP_Post ) {
return false;
}
$content = (string) $post->post_content;
return ( false !== strpos( $content, '[olives_' ) );
}

public function enqueue_front_assets() {
if ( ! $this->should_load_front_assets() ) {
return;
}
$this->enqueue_shared_assets();
}

public function enqueue_admin_assets( $hook ) {
if ( false === strpos( (string) $hook, 'olives-pharmacy' ) ) {
return;
}
$this->enqueue_shared_assets();
}

private function enqueue_shared_assets() {
wp_enqueue_style( 'olives-inter-font', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap', array(), self::VERSION );
wp_enqueue_script( 'tailwind-play', 'https://cdn.tailwindcss.com?plugins=forms,typography', array(), '3.4.16', false );
wp_add_inline_script( 'tailwind-play', 'tailwind.config = { theme: { extend: { colors: { "olives-green":"#16a34a", "olives-red":"#dc2626" } } } };', 'before' );
wp_enqueue_script( 'iconify', 'https://code.iconify.design/3/3.1.1/iconify.min.js', array(), '3.1.1', true );
wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js', array(), '4.4.3', true );

wp_register_style( 'olives-inline-style', false, array(), self::VERSION );
wp_enqueue_style( 'olives-inline-style' );
wp_add_inline_style( 'olives-inline-style', $this->inline_css() );

wp_register_script( 'olives-app', '', array( 'chartjs' ), self::VERSION, true );
wp_enqueue_script( 'olives-app' );
			wp_localize_script(
				'olives-app',
				'OlivesData',
				array(
					'business'    => $this->receipt_profile(),
					'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
'nonce'       => wp_create_nonce( self::NONCE ),
'userCanRead' => is_user_logged_in() ? current_user_can( 'read' ) : false,
'i18n'        => array(
'login'        => __( 'Please log in to access this page.', 'olives-pharmacy' ),
'saleSuccess'  => __( 'Sale completed successfully.', 'olives-pharmacy' ),
'lowStock'     => __( 'Low', 'olives-pharmacy' ),
'expiringSoon' => __( 'Expiring soon', 'olives-pharmacy' ),
'expired'      => __( 'Expired', 'olives-pharmacy' ),
),
)
);
wp_add_inline_script( 'olives-app', $this->inline_js() );
}

private function inline_css() {
return '
.olives-app{font-family:Inter,system-ui,sans-serif;color:#0f172a;background:#f8fafc;padding:16px;border-radius:20px}
.olives-card{border-radius:20px;background:#fff;box-shadow:0 4px 24px rgba(15,23,42,.06);border:1px solid #e2e8f0}
.olives-header{border-radius:20px;background:#fff;box-shadow:0 4px 24px rgba(15,23,42,.06);border:1px solid #e2e8f0}
.olives-glass{backdrop-filter: blur(10px);background:rgba(255,255,255,.65);border:1px solid rgba(255,255,255,.4)}
.olives-table-wrap{overflow:auto}
.olives-skeleton{position:relative;overflow:hidden;background:#e2e8f0}
.olives-skeleton:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,.5),transparent);animation:olives-shimmer 1.4s infinite}
@keyframes olives-shimmer{0%{transform:translateX(-100%)}100%{transform:translateX(100%)}}
@media (prefers-reduced-motion: reduce){*{animation:none!important;transition:none!important}}
';
}

private function nav_links( $active ) {
$pages = get_option( 'olives_pages', array() );
$links = array();
foreach ( $this->pages as $key => $page ) {
if ( ! isset( $pages[ $key ] ) ) {
continue;
}
$links[] = array(
'key'    => $key,
'label'  => str_replace( 'Olives — ', '', $page['title'] ),
'url'    => get_permalink( (int) $pages[ $key ] ),
'active' => $active === $key,
);
}
return $links;
}

private function render_header( $active = 'home' ) {
$user  = wp_get_current_user();
$name  = 'Guest';
$greet = __( 'Welcome, Guest', 'olives-pharmacy' );
if ( $user instanceof WP_User && $user->exists() ) {
$name  = ! empty( $user->display_name ) ? $user->display_name : $user->user_login;
$greet = sprintf( __( 'Welcome back, %s', 'olives-pharmacy' ), $name );
}

$tz   = new DateTimeZone( 'Africa/Lagos' );
$now  = new DateTime( 'now', $tz );
$date = $now->format( 'l, d F Y' );

$nav = $this->nav_links( $active );

ob_start();
?>
<div class="olives-header p-4 md:p-5 mb-4">
<div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
<div class="flex items-center gap-2 text-olives-green font-semibold text-lg">
<iconify-icon icon="solar:leaf-linear"></iconify-icon>
<span><?php echo esc_html__( 'Olives Pharmacy', 'olives-pharmacy' ); ?></span>
</div>
<div class="text-slate-600 font-medium"><?php echo esc_html( $date ); ?></div>
<div class="flex flex-wrap items-center gap-2 text-sm">
<span class="font-mono text-slate-900 olives-wat-clock">00:00:00</span>
<span class="px-2 py-1 rounded-full bg-slate-100 text-slate-600">WAT</span>
<span class="px-3 py-1 rounded-full bg-green-50 text-olives-green"><?php echo esc_html( $greet ); ?></span>
</div>
</div>
<?php if ( ! empty( $nav ) ) : ?>
<div class="mt-4 flex flex-wrap gap-2">
<?php foreach ( $nav as $link ) : ?>
<a class="px-4 py-2 rounded-full border transition-all duration-200 <?php echo $link['active'] ? 'bg-olives-green text-white border-olives-green' : 'bg-white text-slate-700 border-slate-200 hover:border-olives-green hover:text-olives-green'; ?>" href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<?php
return ob_get_clean();
}

private function wrapper_start( $page ) {
return '<div class="olives-app" data-olives-page="' . esc_attr( $page ) . '">' . $this->render_header( $page );
}

private function wrapper_end() {
return '</div>';
}

private function login_required_card() {
return '<div class="olives-card p-6 text-center"><iconify-icon class="text-4xl text-slate-400" icon="solar:lock-keyhole-linear"></iconify-icon><h3 class="text-xl font-semibold mt-2">' . esc_html__( 'Access Required', 'olives-pharmacy' ) . '</h3><p class="text-slate-500 mt-1">' . esc_html__( 'Please log in to continue.', 'olives-pharmacy' ) . '</p></div>';
}

public function shortcode_home() {
$html  = $this->wrapper_start( 'home' );
$html .= '<section class="relative overflow-hidden rounded-[20px] p-6 md:p-10 bg-gradient-to-br from-green-100 via-red-100 to-white">';
$html .= '<div class="absolute -top-8 -left-8 w-44 h-44 rounded-full bg-green-300/30 blur-2xl"></div><div class="absolute -bottom-8 -right-8 w-44 h-44 rounded-full bg-red-300/30 blur-2xl"></div>';
$html .= '<div class="relative"><div class="text-center mb-8"><div class="inline-flex items-center gap-2 text-3xl font-extrabold text-olives-green"><iconify-icon icon="solar:leaf-linear"></iconify-icon><span>OLIVES PHARMACY</span></div><p class="text-slate-600 mt-2">Trusted Care, Every Day</p></div>';
$html .= '<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">';
$cards = array(
'dashboard' => array( 'solar:widget-2-linear', __( 'Dashboard', 'olives-pharmacy' ), __( 'Overview and quick statistics', 'olives-pharmacy' ) ),
'stock'     => array( 'solar:box-linear', __( 'Stock', 'olives-pharmacy' ), __( 'Manage products and inventory', 'olives-pharmacy' ) ),
'sales'     => array( 'solar:cart-large-2-linear', __( 'Sales', 'olives-pharmacy' ), __( 'Process sales and print receipts', 'olives-pharmacy' ) ),
'financial' => array( 'solar:wallet-money-linear', __( 'Financial', 'olives-pharmacy' ), __( 'Revenue and transaction history', 'olives-pharmacy' ) ),
'analytics' => array( 'solar:chart-2-linear', __( 'Analytics', 'olives-pharmacy' ), __( 'Trends, insights and activity', 'olives-pharmacy' ) ),
);
$nav   = $this->nav_links( 'home' );
$urls  = wp_list_pluck( $nav, 'url', 'key' );
foreach ( $cards as $key => $card ) {
$html .= '<a href="' . esc_url( isset( $urls[ $key ] ) ? $urls[ $key ] : '#' ) . '" class="olives-glass rounded-[20px] p-4 transition-all duration-200 hover:-translate-y-1 hover:shadow-lg">';
$html .= '<iconify-icon class="text-2xl text-olives-green" icon="' . esc_attr( $card[0] ) . '"></iconify-icon>';
$html .= '<h3 class="font-semibold mt-3">' . esc_html( $card[1] ) . '</h3><p class="text-sm text-slate-600 mt-1">' . esc_html( $card[2] ) . '</p>';
$html .= '<div class="mt-3 text-olives-green text-sm font-medium">Open →</div></a>';
}
$html .= '</div></div></section>';
$html .= $this->wrapper_end();
return $html;
}

public function shortcode_dashboard() {
$html = $this->wrapper_start( 'dashboard' );
if ( ! is_user_logged_in() ) {
return $html . $this->login_required_card() . $this->wrapper_end();
}
$html .= '<section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-4" id="olives-dashboard-stats"></section>';
$html .= '<section class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">';
$html .= '<a href="#" class="olives-card p-4" data-olives-link="stock"><h4 class="font-semibold">Stock</h4><p class="text-sm text-slate-500">Manage inventory</p></a>';
$html .= '<a href="#" class="olives-card p-4" data-olives-link="sales"><h4 class="font-semibold">Sales</h4><p class="text-sm text-slate-500">Create new sale</p></a>';
$html .= '<a href="#" class="olives-card p-4" data-olives-link="analytics"><h4 class="font-semibold">Analytics</h4><p class="text-sm text-slate-500">View trends</p></a>';
$html .= '</section>';
$html .= '<section class="olives-card p-5"><div class="flex justify-between items-center"><h3 class="font-semibold text-lg">Recent Activity</h3><button class="px-3 py-2 rounded-[20px] border text-sm">View Full History</button></div><div id="olives-dashboard-activity" class="mt-4 space-y-2"></div></section>';
$html .= $this->wrapper_end();
return $html;
}

public function shortcode_stock() {
$html = $this->wrapper_start( 'stock' );
if ( ! is_user_logged_in() ) {
return $html . $this->login_required_card() . $this->wrapper_end();
}
$html .= '<section class="olives-card p-4 mb-4"><div class="flex flex-col md:flex-row md:items-center gap-3 md:justify-between">';
$html .= '<div class="relative w-full md:max-w-md"><iconify-icon icon="solar:magnifer-linear" class="absolute left-3 top-3 text-slate-400"></iconify-icon><input id="olives-stock-search" class="w-full pl-10 pr-3 py-2 rounded-[20px] border-slate-200" placeholder="Search products..." /></div>';
$html .= '<div class="flex gap-2"><button id="olives-add-product" class="px-4 py-2 rounded-[20px] bg-olives-green text-white">Add Product</button><button id="olives-export-products" class="px-4 py-2 rounded-[20px] border">Export CSV</button></div></div>';
$html .= '<div class="olives-table-wrap mt-4"><table class="w-full text-sm"><thead><tr class="text-left text-slate-500"><th class="p-2">Name</th><th class="p-2">NAFDAC</th><th class="p-2">Batch</th><th class="p-2">Expiry</th><th class="p-2">Qty</th><th class="p-2">Cost</th><th class="p-2">Selling</th><th class="p-2">Reorder</th><th class="p-2">Actions</th></tr></thead><tbody id="olives-stock-tbody"></tbody></table></div></section>';
$html .= '<section class="olives-card p-5"><div class="flex justify-between items-center"><h3 class="font-semibold text-lg">Stock Movement History</h3><button id="olives-stock-full-history" class="px-3 py-2 rounded-[20px] border text-sm">View Full History</button></div><div class="olives-table-wrap mt-4"><table class="w-full text-sm"><thead><tr class="text-left text-slate-500"><th class="p-2">Product</th><th class="p-2">Type</th><th class="p-2">Change</th><th class="p-2">After</th><th class="p-2">Staff</th><th class="p-2">Time</th></tr></thead><tbody id="olives-stock-history"></tbody></table></div></section>';
$html .= $this->modal_markup();
$html .= $this->wrapper_end();
return $html;
}

public function shortcode_sales() {
$html = $this->wrapper_start( 'sales' );
if ( ! is_user_logged_in() ) {
return $html . $this->login_required_card() . $this->wrapper_end();
}
$html .= '<section class="grid grid-cols-1 xl:grid-cols-5 gap-4">';
$html .= '<div class="xl:col-span-3 olives-card p-4"><div class="relative mb-4"><iconify-icon icon="solar:magnifer-linear" class="absolute left-3 top-3 text-slate-400"></iconify-icon><input id="olives-sales-search" class="w-full pl-10 pr-3 py-2 rounded-[20px] border-slate-200" placeholder="Search products" /></div><div id="olives-sales-products" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div></div>';
$html .= '<div class="xl:col-span-2 olives-card p-4"><h3 class="font-semibold text-lg mb-3">Cart</h3><div class="olives-table-wrap"><table class="w-full text-sm"><thead><tr class="text-left text-slate-500"><th class="p-2">Item</th><th class="p-2">Price</th><th class="p-2">Qty</th><th class="p-2">Total</th><th class="p-2">✕</th></tr></thead><tbody id="olives-cart-body"></tbody></table></div><div class="mt-4 space-y-2 text-sm"><div class="flex justify-between"><span>Subtotal</span><strong id="olives-subtotal">₦0</strong></div><div class="flex justify-between text-lg text-olives-green font-bold"><span>Grand Total</span><strong id="olives-grandtotal">₦0</strong></div></div><div class="mt-4"><label class="text-sm font-medium">Payment Method</label><select id="olives-payment-method" class="w-full mt-1 rounded-[20px] border-slate-200"><option value="cash">Cash</option><option value="card">Card</option><option value="transfer">Transfer</option><option value="cash_transfer">Cash + Transfer</option><option value="cash_card">Cash + Card</option><option value="transfer_card">Transfer + Card</option><option value="cash_transfer_card">Cash + Transfer + Card</option></select><div id="olives-split-fields" class="mt-3 space-y-2"></div><p id="olives-balance-msg" class="text-sm mt-2"></p></div><button id="olives-complete-sale" class="w-full mt-4 px-4 py-3 rounded-[20px] bg-olives-green text-white font-semibold disabled:opacity-50" disabled><iconify-icon icon="solar:check-circle-linear"></iconify-icon> COMPLETE SALE</button><button class="w-full mt-2 px-4 py-2 rounded-[20px] border text-sm">View Full History</button></div>';
$html .= '</section>';
$html .= $this->receipt_modal_markup();
$html .= $this->wrapper_end();
return $html;
}

public function shortcode_financial() {
$html = $this->wrapper_start( 'financial' );
if ( ! is_user_logged_in() ) {
return $html . $this->login_required_card() . $this->wrapper_end();
}
$html .= '<section class="olives-card p-4 mb-4"><div class="flex flex-wrap gap-2" id="olives-financial-filters"><button data-range="today" class="px-3 py-2 rounded-full bg-olives-green text-white">Today</button><button data-range="week" class="px-3 py-2 rounded-full border">This Week</button><button data-range="month" class="px-3 py-2 rounded-full border">This Month</button><button data-range="year" class="px-3 py-2 rounded-full border">This Year</button><button data-range="custom" class="px-3 py-2 rounded-full border">Custom</button></div><div id="olives-custom-range" class="hidden mt-3 flex flex-wrap gap-2"><input type="date" id="olives-from" class="rounded-[20px] border-slate-200" /><input type="date" id="olives-to" class="rounded-[20px] border-slate-200" /><button id="olives-apply-custom" class="px-4 py-2 rounded-[20px] bg-olives-green text-white">Apply</button></div></section>';
$html .= '<section class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4" id="olives-financial-kpis"></section>';
$html .= '<section class="olives-card p-5"><div class="flex justify-between items-center"><h3 class="font-semibold text-lg">Transaction History</h3><button class="px-3 py-2 rounded-[20px] border text-sm">View Full History</button></div><div class="olives-table-wrap mt-4"><table class="w-full text-sm"><thead><tr class="text-left text-slate-500"><th class="p-2">Receipt No</th><th class="p-2">Date/Time</th><th class="p-2">Items</th><th class="p-2">Payment</th><th class="p-2">Total</th><th class="p-2">Staff</th></tr></thead><tbody id="olives-financial-history"></tbody></table></div></section>';
$html .= $this->receipt_modal_markup();
$html .= $this->wrapper_end();
return $html;
}

public function shortcode_analytics() {
$html = $this->wrapper_start( 'analytics' );
if ( ! is_user_logged_in() ) {
return $html . $this->login_required_card() . $this->wrapper_end();
}
$html .= '<section class="olives-card p-5 mb-4"><div class="flex items-center justify-between"><h3 class="font-semibold text-lg">Revenue Trend</h3><div class="flex gap-2" id="olives-analytics-days"><button class="px-3 py-2 rounded-full bg-olives-green text-white" data-days="7">7 Days</button><button class="px-3 py-2 rounded-full border" data-days="30">30 Days</button><button class="px-3 py-2 rounded-full border" data-days="90">90 Days</button></div></div><canvas id="olives-revenue-chart" height="100" class="mt-4"></canvas></section>';
$html .= '<section class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4"><div class="olives-card p-5"><h3 class="font-semibold text-lg">Top Products</h3><div id="olives-top-products" class="space-y-3 mt-3"></div></div><div class="olives-card p-5"><h3 class="font-semibold text-lg">Key Insights</h3><div id="olives-insights" class="space-y-2 mt-3"></div></div></section>';
$html .= '<section class="olives-card p-5"><div class="flex justify-between items-center"><h3 class="font-semibold text-lg">Activity / System Log</h3><button class="px-3 py-2 rounded-[20px] border text-sm">View Full Activity Log</button></div><div id="olives-activity-log" class="space-y-2 mt-4"></div></section>';
$html .= $this->wrapper_end();
return $html;
}

private function modal_markup() {
return '<div id="olives-product-modal" class="hidden fixed inset-0 bg-slate-900/40 z-50 p-4"><div class="max-w-2xl mx-auto olives-glass rounded-[20px] p-4 bg-white/90"><div class="flex justify-between items-center"><h3 class="font-semibold text-lg">Product</h3><button id="olives-close-product-modal" aria-label="Close">✕</button></div><form id="olives-product-form" class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3"><input type="hidden" name="id" /><input name="name" placeholder="Name" class="rounded-[20px] border-slate-200" required /><input name="nafdac_number" placeholder="NAFDAC Number" maxlength="20" class="rounded-[20px] border-slate-200" /><input name="batch_number" placeholder="Batch Number" class="rounded-[20px] border-slate-200" /><input type="date" name="expiry_date" class="rounded-[20px] border-slate-200" /><input type="number" name="quantity" min="0" placeholder="Quantity" class="rounded-[20px] border-slate-200" required /><input name="cost_price" placeholder="Cost Price" class="rounded-[20px] border-slate-200" required /><input name="selling_price" placeholder="Selling Price" class="rounded-[20px] border-slate-200" required /><input type="number" name="reorder_level" min="0" placeholder="Reorder Level" class="rounded-[20px] border-slate-200" required /><div class="md:col-span-2 flex justify-end gap-2"><button type="button" id="olives-cancel-product" class="px-4 py-2 rounded-[20px] border">Cancel</button><button type="submit" class="px-4 py-2 rounded-[20px] bg-olives-green text-white">Save Product</button></div></form></div></div>';
}

private function receipt_modal_markup() {
return '<div id="olives-receipt-modal" class="hidden fixed inset-0 bg-slate-900/40 z-50 p-4"><div class="max-w-lg mx-auto olives-card p-4"><div class="flex justify-between items-center"><h3 class="font-semibold text-lg">Receipt</h3><button id="olives-close-receipt" aria-label="Close">✕</button></div><div id="olives-receipt-content" class="mt-3 text-sm"></div><div class="flex gap-2 mt-4"><button id="olives-print-receipt" class="px-4 py-2 rounded-[20px] bg-olives-green text-white">Print</button><button id="olives-new-sale" class="px-4 py-2 rounded-[20px] border">New Sale</button></div></div></div>';
}

private function ensure_nonce() {
if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
wp_send_json_error( array( 'message' => __( 'Invalid request.', 'olives-pharmacy' ) ), 403 );
}
}

private function assert_can_read() {
if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'olives-pharmacy' ) ), 403 );
}
}

private function assert_can_write() {
if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'olives-pharmacy' ) ), 403 );
}
}

private function table( $name ) {
global $wpdb;
return $wpdb->prefix . 'olives_' . $name;
}

		private function now_wat() {
			$dt = new DateTime( 'now', new DateTimeZone( 'Africa/Lagos' ) );
			return $dt->format( 'Y-m-d H:i:s' );
		}

		private function receipt_profile() {
			return array(
				'name'    => (string) apply_filters( 'olives_pharmacy_name', 'OLIVES PHARMACY' ),
				'address' => (string) apply_filters( 'olives_pharmacy_address', 'No. 1 Health Street, Uyo' ),
				'phone'   => (string) apply_filters( 'olives_pharmacy_phone', '+234 800 000 0000' ),
			);
		}

private function format_naira( $amount ) {
return '₦' . number_format( (float) $amount, 0 );
}

public function ajax_products_list() {
$this->ensure_nonce();
$this->assert_can_read();
global $wpdb;
$table = $this->table( 'products' );
$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
wp_send_json_success( array( 'products' => $rows ) );
}

public function ajax_products_save() {
$this->ensure_nonce();
$this->assert_can_write();
global $wpdb;

$id            = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
$name          = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
$nafdac_number = isset( $_POST['nafdac_number'] ) ? sanitize_text_field( wp_unslash( $_POST['nafdac_number'] ) ) : '';
$batch_number  = isset( $_POST['batch_number'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_number'] ) ) : '';
$expiry_date   = isset( $_POST['expiry_date'] ) ? sanitize_text_field( wp_unslash( $_POST['expiry_date'] ) ) : '';
$quantity      = isset( $_POST['quantity'] ) ? absint( wp_unslash( $_POST['quantity'] ) ) : 0;
$cost_price    = isset( $_POST['cost_price'] ) ? floatval( str_replace( ',', '', wp_unslash( $_POST['cost_price'] ) ) ) : 0;
$selling_price = isset( $_POST['selling_price'] ) ? floatval( str_replace( ',', '', wp_unslash( $_POST['selling_price'] ) ) ) : 0;
$reorder_level = isset( $_POST['reorder_level'] ) ? absint( wp_unslash( $_POST['reorder_level'] ) ) : 0;

if ( '' === $name ) {
wp_send_json_error( array( 'message' => __( 'Product name is required.', 'olives-pharmacy' ) ), 422 );
}
if ( strlen( $nafdac_number ) > 20 ) {
wp_send_json_error( array( 'message' => __( 'NAFDAC Number cannot exceed 20 characters.', 'olives-pharmacy' ) ), 422 );
}
if ( strlen( $batch_number ) > 50 ) {
wp_send_json_error( array( 'message' => __( 'Batch Number cannot exceed 50 characters.', 'olives-pharmacy' ) ), 422 );
}

$table = $this->table( 'products' );
$now   = $this->now_wat();
$data  = array(
'name'          => $name,
'nafdac_number' => $nafdac_number,
'batch_number'  => $batch_number,
'expiry_date'   => ! empty( $expiry_date ) ? $expiry_date : null,
'quantity'      => $quantity,
'cost_price'    => $cost_price,
'selling_price' => $selling_price,
'reorder_level' => $reorder_level,
'updated_at'    => $now,
);

if ( $id > 0 ) {
$old = $wpdb->get_row( $wpdb->prepare( "SELECT quantity FROM {$table} WHERE id=%d", $id ), ARRAY_A );
$wpdb->update( $table, $data, array( 'id' => $id ) );
$this->log_stock_history( $id, $name, 'edit', $quantity - (int) $old['quantity'], $quantity, __( 'Product updated', 'olives-pharmacy' ) );
} else {
$data['created_at'] = $now;
$wpdb->insert( $table, $data );
$id = (int) $wpdb->insert_id;
$this->log_stock_history( $id, $name, 'added', $quantity, $quantity, __( 'Product created', 'olives-pharmacy' ) );
}

wp_send_json_success( array( 'id' => $id ) );
}

public function ajax_products_delete() {
$this->ensure_nonce();
$this->assert_can_write();
global $wpdb;
$id    = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
$table = $this->table( 'products' );
$item  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $id ), ARRAY_A );
if ( ! $item ) {
wp_send_json_error( array( 'message' => __( 'Product not found.', 'olives-pharmacy' ) ), 404 );
}
$wpdb->delete( $table, array( 'id' => $id ) );
$this->log_stock_history( $id, $item['name'], 'adjustment', -(int) $item['quantity'], 0, __( 'Product deleted', 'olives-pharmacy' ) );
wp_send_json_success();
}

private function log_stock_history( $product_id, $product_name, $type, $change, $after, $note = '' ) {
global $wpdb;
$wpdb->insert(
$this->table( 'stock_history' ),
array(
'product_id'       => absint( $product_id ),
'product_name'     => sanitize_text_field( $product_name ),
'change_type'      => sanitize_text_field( $type ),
'quantity_change'  => (int) $change,
'quantity_after'   => (int) $after,
'note'             => sanitize_text_field( $note ),
'staff_id'         => ( get_current_user_id() > 0 ) ? get_current_user_id() : null,
'created_at'       => $this->now_wat(),
)
);
}

private function generate_receipt_no() {
global $wpdb;
$table = $this->table( 'sales' );
$date  = ( new DateTime( 'now', new DateTimeZone( 'Africa/Lagos' ) ) )->format( 'Ymd' );
for ( $i = 0; $i < 6; $i++ ) {
$suffix     = wp_rand( 1000, 9999 );
$receipt_no = 'OP-' . $date . '-' . $suffix;
$exists     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE receipt_no=%s", $receipt_no ) );
if ( 0 === $exists ) {
return $receipt_no;
}
}
return 'OP-' . $date . '-' . wp_rand( 10000, 99999 );
}

public function ajax_sales_create() {
$this->ensure_nonce();
$this->assert_can_read();
global $wpdb;

$items_raw       = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : '[]';
$payment_method  = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : 'cash';
$cash_amount     = isset( $_POST['cash_amount'] ) ? floatval( wp_unslash( $_POST['cash_amount'] ) ) : 0;
$card_amount     = isset( $_POST['card_amount'] ) ? floatval( wp_unslash( $_POST['card_amount'] ) ) : 0;
$transfer_amount = isset( $_POST['transfer_amount'] ) ? floatval( wp_unslash( $_POST['transfer_amount'] ) ) : 0;

$items = json_decode( $items_raw, true );
if ( ! is_array( $items ) || empty( $items ) ) {
wp_send_json_error( array( 'message' => __( 'Cart is empty.', 'olives-pharmacy' ) ), 422 );
}

$products_table   = $this->table( 'products' );
$sales_table      = $this->table( 'sales' );
$sale_items_table = $this->table( 'sale_items' );
$total            = 0;
$validated_items  = array();

foreach ( $items as $item ) {
$product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
$qty        = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 0;
$price      = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;
if ( $product_id < 1 || $qty < 1 || $price < 0 ) {
wp_send_json_error( array( 'message' => __( 'Invalid cart item.', 'olives-pharmacy' ) ), 422 );
}

$product = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$products_table} WHERE id=%d", $product_id ), ARRAY_A );
if ( ! $product ) {
wp_send_json_error( array( 'message' => __( 'Product not found.', 'olives-pharmacy' ) ), 404 );
}
if ( (int) $product['quantity'] < $qty ) {
wp_send_json_error( array( 'message' => sprintf( __( 'Insufficient stock for %s', 'olives-pharmacy' ), $product['name'] ) ), 422 );
}

$line_total         = $qty * $price;
$total             += $line_total;
$validated_items[]  = array(
'product'    => $product,
'quantity'   => $qty,
'unit_price' => $price,
'line_total' => $line_total,
);
}

$split_sum = $cash_amount + $card_amount + $transfer_amount;
if ( abs( $split_sum - $total ) > self::PAYMENT_TOLERANCE ) {
wp_send_json_error( array( 'message' => __( 'Payment split is not balanced.', 'olives-pharmacy' ) ), 422 );
}

$user       = wp_get_current_user();
$staff_name = $user && $user->exists() ? ( empty( $user->display_name ) ? $user->user_login : $user->display_name ) : 'Guest';
$receipt_no = $this->generate_receipt_no();
$created_at = $this->now_wat();

// Transactions are best-effort; some DB engines may not support them.
$tx_result  = $wpdb->query( 'START TRANSACTION' );
$tx_started = ( false !== $tx_result && empty( $wpdb->last_error ) );
try {
$insert_sale = $wpdb->insert(
$sales_table,
array(
'receipt_no'       => $receipt_no,
'total_amount'     => $total,
'payment_method'   => $payment_method,
'cash_amount'      => $cash_amount,
'card_amount'      => $card_amount,
'transfer_amount'  => $transfer_amount,
'staff_id'         => get_current_user_id(),
'staff_name'       => $staff_name,
'created_at'       => $created_at,
)
);
if ( false === $insert_sale ) {
throw new RuntimeException( __( 'Failed to create sale.', 'olives-pharmacy' ) . ' ' . $wpdb->last_error );
}
$sale_id = (int) $wpdb->insert_id;

foreach ( $validated_items as $item ) {
$after_qty = (int) $item['product']['quantity'] - (int) $item['quantity'];
$insert_item = $wpdb->insert(
$sale_items_table,
array(
'sale_id'       => $sale_id,
'product_id'    => (int) $item['product']['id'],
'product_name'  => $item['product']['name'],
'unit_price'    => $item['unit_price'],
'quantity'      => (int) $item['quantity'],
'line_total'    => $item['line_total'],
)
);
if ( false === $insert_item ) {
throw new RuntimeException( __( 'Failed to create sale item.', 'olives-pharmacy' ) . ' ' . $wpdb->last_error );
}
$update_stock = $wpdb->update( $products_table, array( 'quantity' => $after_qty, 'updated_at' => $created_at ), array( 'id' => (int) $item['product']['id'] ) );
if ( false === $update_stock ) {
throw new RuntimeException( __( 'Failed to update stock.', 'olives-pharmacy' ) . ' ' . $wpdb->last_error );
}
$this->log_stock_history( (int) $item['product']['id'], $item['product']['name'], 'sale', - (int) $item['quantity'], $after_qty, 'Sale ' . $receipt_no );
if ( ! empty( $wpdb->last_error ) ) {
throw new RuntimeException( __( 'Failed to log stock history.', 'olives-pharmacy' ) . ' ' . $wpdb->last_error );
}
}

if ( $tx_started ) {
$wpdb->query( 'COMMIT' );
}
} catch ( Exception $e ) {
if ( $tx_started ) {
$wpdb->query( 'ROLLBACK' );
}
wp_send_json_error( array( 'message' => __( 'Unable to process sale.', 'olives-pharmacy' ) ), 500 );
}

$payload = array(
'receipt_no'       => $receipt_no,
'total_amount'     => $total,
'payment_method'   => $payment_method,
'cash_amount'      => $cash_amount,
'card_amount'      => $card_amount,
'transfer_amount'  => $transfer_amount,
'staff_name'       => $staff_name,
'created_at'       => $created_at,
'items'            => array_map(
function( $it ) {
return array(
'product_name' => $it['product']['name'],
'unit_price'   => $it['unit_price'],
'quantity'     => $it['quantity'],
'line_total'   => $it['line_total'],
);
},
$validated_items
),
);

wp_send_json_success( $payload );
}

public function ajax_sales_list() {
$this->ensure_nonce();
$this->assert_can_read();
global $wpdb;
$table = $this->table( 'sales' );
$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
wp_send_json_success( array( 'sales' => $rows ) );
}

public function ajax_sales_get() {
$this->ensure_nonce();
$this->assert_can_read();
global $wpdb;
$id         = isset( $_POST['sale_id'] ) ? absint( wp_unslash( $_POST['sale_id'] ) ) : 0;
$sales      = $this->table( 'sales' );
$sale_items = $this->table( 'sale_items' );
$sale       = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$sales} WHERE id=%d", $id ), ARRAY_A );
if ( ! $sale ) {
wp_send_json_error( array( 'message' => __( 'Sale not found.', 'olives-pharmacy' ) ), 404 );
}
$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$sale_items} WHERE sale_id=%d", $id ), ARRAY_A );
wp_send_json_success( array( 'sale' => $sale, 'items' => $items ) );
}

public function ajax_stock_history_list() {
$this->ensure_nonce();
$this->assert_can_read();
global $wpdb;
$table = $this->table( 'stock_history' );
$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 500", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
wp_send_json_success( array( 'history' => $rows ) );
}

public function ajax_dashboard_stats() {
$this->ensure_nonce();
$this->assert_can_read();
global $wpdb;
$products = $this->table( 'products' );
$sales    = $this->table( 'sales' );
$today    = ( new DateTime( 'now', new DateTimeZone( 'Africa/Lagos' ) ) )->format( 'Y-m-d' );

$total_products = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$products}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$total_stock    = (int) $wpdb->get_var( "SELECT COALESCE(SUM(quantity),0) FROM {$products}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$low_stock      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$products} WHERE quantity <= reorder_level" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$today_sales    = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(total_amount),0) FROM {$sales} WHERE DATE(created_at)=%s", $today ) );

$activity = $wpdb->get_results(
"(
SELECT created_at, CONCAT('Sale ', receipt_no, ' completed') AS action, staff_name AS staff FROM {$sales}
)
UNION ALL
(
SELECT created_at, CONCAT(product_name, ' stock ', change_type) AS action, '' AS staff FROM " . $this->table( 'stock_history' ) . '
)
ORDER BY created_at DESC LIMIT 8',
ARRAY_A
); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

wp_send_json_success(
array(
'total_products' => $total_products,
'total_stock'    => $total_stock,
'low_stock'      => $low_stock,
'today_sales'    => $today_sales,
'activity'       => $activity,
)
);
}

public function ajax_financial_summary() {
$this->ensure_nonce();
$this->assert_can_read();
global $wpdb;

$range = isset( $_POST['range'] ) ? sanitize_text_field( wp_unslash( $_POST['range'] ) ) : 'today';
$from  = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
$to    = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';

$period = $this->resolve_period( $range, $from, $to );
$table  = $this->table( 'sales' );

$totals = $wpdb->get_row(
$wpdb->prepare(
"SELECT COALESCE(SUM(total_amount),0) AS revenue, COUNT(*) AS txns, COALESCE(AVG(total_amount),0) AS avg_sale
FROM {$table}
WHERE created_at BETWEEN %s AND %s",
$period['start'],
$period['end']
),
ARRAY_A
);

$history = $wpdb->get_results(
$wpdb->prepare(
"SELECT s.*, (SELECT COALESCE(SUM(quantity),0) FROM " . $this->table( 'sale_items' ) . " si WHERE si.sale_id=s.id) AS item_count
FROM {$table} s
WHERE s.created_at BETWEEN %s AND %s
ORDER BY s.created_at DESC
LIMIT 200",
$period['start'],
$period['end']
),
ARRAY_A
);

wp_send_json_success( array( 'totals' => $totals, 'history' => $history ) );
}

private function resolve_period( $range, $from, $to ) {
$tz  = new DateTimeZone( 'Africa/Lagos' );
$now = new DateTime( 'now', $tz );

switch ( $range ) {
case 'week':
$start = ( clone $now )->modify( 'monday this week' )->setTime( 0, 0, 0 );
$end   = ( clone $now )->setTime( 23, 59, 59 );
break;
case 'month':
$start = ( clone $now )->modify( 'first day of this month' )->setTime( 0, 0, 0 );
$end   = ( clone $now )->setTime( 23, 59, 59 );
break;
case 'year':
$start = ( clone $now )->setDate( (int) $now->format( 'Y' ), 1, 1 )->setTime( 0, 0, 0 );
$end   = ( clone $now )->setTime( 23, 59, 59 );
break;
case 'custom':
$start = DateTime::createFromFormat( 'Y-m-d', $from, $tz );
$end   = DateTime::createFromFormat( 'Y-m-d', $to, $tz );
if ( ! $start || ! $end ) {
$start = ( clone $now )->setTime( 0, 0, 0 );
$end   = ( clone $now )->setTime( 23, 59, 59 );
} else {
$start->setTime( 0, 0, 0 );
$end->setTime( 23, 59, 59 );
}
break;
case 'today':
default:
$start = ( clone $now )->setTime( 0, 0, 0 );
$end   = ( clone $now )->setTime( 23, 59, 59 );
break;
}
return array(
'start' => $start->format( 'Y-m-d H:i:s' ),
'end'   => $end->format( 'Y-m-d H:i:s' ),
);
}

public function ajax_analytics_data() {
$this->ensure_nonce();
$this->assert_can_read();
global $wpdb;
$days       = isset( $_POST['days'] ) ? absint( wp_unslash( $_POST['days'] ) ) : 30;
$days       = in_array( $days, array( 7, 30, 90 ), true ) ? $days : 30;
$sales      = $this->table( 'sales' );
$sale_items = $this->table( 'sale_items' );
$products   = $this->table( 'products' );

$trend = $wpdb->get_results(
$wpdb->prepare(
"SELECT DATE(created_at) AS day, COALESCE(SUM(total_amount),0) AS total
FROM {$sales}
WHERE created_at >= DATE_SUB(%s, INTERVAL %d DAY)
GROUP BY DATE(created_at)
ORDER BY day ASC",
$this->now_wat(),
$days
),
ARRAY_A
);

$top_products = $wpdb->get_results(
$wpdb->prepare(
"SELECT product_name, SUM(quantity) AS qty_sold, SUM(line_total) AS revenue
FROM {$sale_items}
WHERE sale_id IN (SELECT id FROM {$sales} WHERE created_at >= DATE_SUB(%s, INTERVAL %d DAY))
GROUP BY product_name
ORDER BY qty_sold DESC
LIMIT 5",
$this->now_wat(),
$days
),
ARRAY_A
);

$best_day = $wpdb->get_row(
$wpdb->prepare(
"SELECT DATE(created_at) as day, AVG(total_amount) as avg_total FROM {$sales} WHERE created_at >= DATE_SUB(%s, INTERVAL %d DAY) GROUP BY DATE(created_at) ORDER BY avg_total DESC LIMIT 1",
$this->now_wat(),
$days
),
ARRAY_A
);
$stockouts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$products} WHERE quantity <= 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

$best_day_name = __( 'N/A', 'olives-pharmacy' );
if ( $best_day && ! empty( $best_day['day'] ) ) {
$best_day_name = wp_date( 'l', strtotime( $best_day['day'] ), new DateTimeZone( 'Africa/Lagos' ) );
}
$best_day_avg = $this->format_naira( $best_day ? $best_day['avg_total'] : 0 );

$insights = array(
sprintf( __( 'Best day: %1$s — %2$s avg', 'olives-pharmacy' ), $best_day_name, $best_day_avg ),
sprintf( __( 'Most profitable product: %s', 'olives-pharmacy' ), ! empty( $top_products ) ? $top_products[0]['product_name'] : __( 'N/A', 'olives-pharmacy' ) ),
sprintf( __( 'Stockouts this window: %d', 'olives-pharmacy' ), $stockouts ),
);

wp_send_json_success( array( 'trend' => $trend, 'top_products' => $top_products, 'insights' => $insights ) );
}

public function ajax_activity_log() {
$this->ensure_nonce();
$this->assert_can_read();
global $wpdb;
$sales = $this->table( 'sales' );
$stock = $this->table( 'stock_history' );

$rows = $wpdb->get_results(
"(
SELECT created_at, CONCAT('Sale ', receipt_no, ' processed') AS action, staff_name AS staff, 'sale' AS type FROM {$sales}
)
UNION ALL
(
SELECT created_at, CONCAT(product_name, ' ', change_type, ' (', quantity_change, ')') AS action, '' AS staff, 'stock' AS type FROM {$stock}
)
ORDER BY created_at DESC
LIMIT 200",
ARRAY_A
); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

wp_send_json_success( array( 'activity' => $rows ) );
}

public function register_admin_menu() {
add_menu_page(
__( 'Olives Pharmacy', 'olives-pharmacy' ),
__( 'Olives Pharmacy', 'olives-pharmacy' ),
'manage_options',
'olives-pharmacy',
array( $this, 'admin_page_home' ),
'dashicons-store',
3
);

add_submenu_page( 'olives-pharmacy', __( 'Home', 'olives-pharmacy' ), __( 'Home', 'olives-pharmacy' ), 'manage_options', 'olives-pharmacy', array( $this, 'admin_page_home' ) );
add_submenu_page( 'olives-pharmacy', __( 'Dashboard', 'olives-pharmacy' ), __( 'Dashboard', 'olives-pharmacy' ), 'manage_options', 'olives-pharmacy-dashboard', array( $this, 'admin_page_dashboard' ) );
add_submenu_page( 'olives-pharmacy', __( 'Stock', 'olives-pharmacy' ), __( 'Stock', 'olives-pharmacy' ), 'manage_options', 'olives-pharmacy-stock', array( $this, 'admin_page_stock' ) );
add_submenu_page( 'olives-pharmacy', __( 'Sales', 'olives-pharmacy' ), __( 'Sales', 'olives-pharmacy' ), 'manage_options', 'olives-pharmacy-sales', array( $this, 'admin_page_sales' ) );
add_submenu_page( 'olives-pharmacy', __( 'Financial', 'olives-pharmacy' ), __( 'Financial', 'olives-pharmacy' ), 'manage_options', 'olives-pharmacy-financial', array( $this, 'admin_page_financial' ) );
add_submenu_page( 'olives-pharmacy', __( 'Analytics', 'olives-pharmacy' ), __( 'Analytics', 'olives-pharmacy' ), 'manage_options', 'olives-pharmacy-analytics', array( $this, 'admin_page_analytics' ) );
}

public function admin_page_home() {
echo do_shortcode( '[olives_home]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
public function admin_page_dashboard() {
echo do_shortcode( '[olives_dashboard]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
public function admin_page_stock() {
echo do_shortcode( '[olives_stock]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
public function admin_page_sales() {
echo do_shortcode( '[olives_sales]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
public function admin_page_financial() {
echo do_shortcode( '[olives_financial]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
public function admin_page_analytics() {
echo do_shortcode( '[olives_analytics]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

private function inline_js() {
return <<<'JS'
(function(){
  const qs = (s, r=document)=>r.querySelector(s);
  const qsa = (s, r=document)=>Array.from(r.querySelectorAll(s));
  const fmt = n => '₦' + (Number(n||0).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ','));
  const parseMoney = v => Number(String(v||'0').replace(/[^\d.]/g,'')) || 0;
  const relTime = date => {
    const sec = Math.floor((Date.now() - new Date(date).getTime()) / 1000);
    if (sec < 60) return `${sec}s ago`;
    if (sec < 3600) return `${Math.floor(sec/60)} mins ago`;
    if (sec < 86400) return `${Math.floor(sec/3600)} hrs ago`;
    return `${Math.floor(sec/86400)} days ago`;
  };

  const updateWATClock = () => {
    const out = new Intl.DateTimeFormat('en-GB', {
      timeZone: 'Africa/Lagos',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false
    }).format(new Date());
    qsa('.olives-wat-clock').forEach(el=>{el.textContent = out;});
  };
  updateWATClock();
  setInterval(updateWATClock, 1000);

  const ajax = async (action, payload={}) => {
    const body = new URLSearchParams({action: 'olives_' + action, nonce: OlivesData.nonce, ...payload});
    const res = await fetch(OlivesData.ajaxUrl, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString()});
    const json = await res.json();
    if (!json.success) throw new Error(json.data?.message || 'Request failed');
    return json.data;
  };

  const pageEl = qs('[data-olives-page]');
  if (!pageEl) return;

  const page = pageEl.getAttribute('data-olives-page');
  const state = { products: [], cart: [], chart: null, autoBalanceIndex: -1 };

  const paymentMap = {
    cash: ['cash'],
    card: ['card'],
    transfer: ['transfer'],
    cash_transfer: ['cash','transfer'],
    cash_card: ['cash','card'],
    transfer_card: ['transfer','card'],
    cash_transfer_card: ['cash','transfer','card']
  };

  const renderDashboard = async () => {
    const statsWrap = qs('#olives-dashboard-stats');
    if (!statsWrap) return;
    statsWrap.innerHTML = '<div class="h-24 olives-skeleton rounded-[20px]"></div><div class="h-24 olives-skeleton rounded-[20px]"></div><div class="h-24 olives-skeleton rounded-[20px]"></div><div class="h-24 olives-skeleton rounded-[20px]"></div>';
    try {
      const data = await ajax('dashboard_stats');
      const cards = [
        ['solar:box-linear','Total Products',data.total_products,false],
        ['solar:layers-linear','Total Stock',data.total_stock,false],
        ['solar:danger-circle-linear','Low Stock Alerts',data.low_stock,data.low_stock>0],
        ['solar:wallet-money-linear','Today\'s Sales',fmt(data.today_sales),false]
      ];
      statsWrap.innerHTML = cards.map(c=>`<div class="olives-card p-4 ${c[3]?'border-olives-red':''}"><div class="flex items-center justify-between"><div><p class="text-slate-500 text-sm">${c[1]}</p><h3 class="text-2xl font-bold mt-1">${c[2]}</h3><p class="text-xs ${c[3]?'text-olives-red':'text-olives-green'} mt-1">${c[3]?'Needs attention':'Healthy'}</p></div><iconify-icon class="text-2xl ${c[3]?'text-olives-red':'text-olives-green'}" icon="${c[0]}"></iconify-icon></div></div>`).join('');
      const activityWrap = qs('#olives-dashboard-activity');
      if (activityWrap) {
        activityWrap.innerHTML = data.activity.map(a=>`<div class="p-3 rounded-[20px] bg-slate-50 border border-slate-200"><div class="text-sm">${a.action}</div><div class="text-xs text-slate-500 mt-1">${a.staff || 'System'} • ${relTime(a.created_at)}</div></div>`).join('') || '<p class="text-sm text-slate-500">No activity yet.</p>';
      }
    } catch(e){ statsWrap.innerHTML = `<p class="text-olives-red">${e.message}</p>`; }
  };

  const renderStock = async () => {
    const tbody = qs('#olives-stock-tbody');
    if (!tbody) return;
    try {
      const data = await ajax('products_list');
      state.products = data.products || [];
      const today = new Date();
      const buildRows = list => list.map(p=>{
        const qty = Number(p.quantity||0);
        const reorder = Number(p.reorder_level||0);
        const low = qty <= reorder;
        let exp = '';
        if (p.expiry_date) {
          const exd = new Date(p.expiry_date + 'T00:00:00');
          const diff = Math.floor((exd.getTime()-today.getTime())/86400000);
          if (diff < 0) exp = '<span class="ml-1 px-2 py-1 rounded-full text-xs bg-red-100 text-red-700">Expired</span>';
          else if (diff <= 30) exp = '<span class="ml-1 px-2 py-1 rounded-full text-xs bg-amber-100 text-amber-700">Expiring soon</span>';
        }
        return `<tr class="border-b ${low?'bg-red-50':''}" data-row="${p.id}"><td class="p-2 font-medium">${p.name}${low?'<span class="ml-1 px-2 py-1 rounded-full text-xs bg-red-100 text-red-700">Low</span>':''}</td><td class="p-2">${p.nafdac_number||'-'}</td><td class="p-2">${p.batch_number||'-'}</td><td class="p-2">${p.expiry_date||'-'}${exp}</td><td class="p-2">${qty}</td><td class="p-2">${fmt(p.cost_price)}</td><td class="p-2">${fmt(p.selling_price)}</td><td class="p-2">${reorder}</td><td class="p-2"><button class="text-olives-green mr-2" data-edit="${p.id}">Edit</button><button class="text-olives-red" data-del="${p.id}">Delete</button></td></tr>`;
      }).join('');
      tbody.innerHTML = buildRows(state.products);
      const search = qs('#olives-stock-search');
      if (search) search.oninput = () => {
        const q = search.value.toLowerCase().trim();
        tbody.innerHTML = buildRows(state.products.filter(p => [p.name,p.nafdac_number,p.batch_number].join(' ').toLowerCase().includes(q)));
      };
      bindStockActions();
      await renderStockHistory();
    } catch(e){ tbody.innerHTML = `<tr><td class="p-2 text-olives-red" colspan="9">${e.message}</td></tr>`; }
  };

  const bindStockActions = () => {
    const modal = qs('#olives-product-modal');
    const form = qs('#olives-product-form');
    const open = (row={}) => {
      if (!modal || !form) return;
      modal.classList.remove('hidden');
      ['id','name','nafdac_number','batch_number','expiry_date','quantity','cost_price','selling_price','reorder_level'].forEach(k=>{
        const f = form.querySelector(`[name="${k}"]`);
        if (f) f.value = row[k] ?? '';
      });
    };
    qs('#olives-add-product')?.addEventListener('click', ()=>open({}));
    qs('#olives-close-product-modal')?.addEventListener('click', ()=>modal.classList.add('hidden'));
    qs('#olives-cancel-product')?.addEventListener('click', ()=>modal.classList.add('hidden'));
    qsa('[data-edit]').forEach(btn=>btn.onclick = () => {
      const row = state.products.find(p=>String(p.id)===btn.dataset.edit);
      if (row) open(row);
    });
    qsa('[data-del]').forEach(btn=>btn.onclick = async () => {
      if (!confirm('Delete this product?')) return;
      try { await ajax('products_delete',{id: btn.dataset.del}); await renderStock(); } catch(e){ alert(e.message); }
    });
    form?.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const fd = new FormData(form);
      const payload = {};
      fd.forEach((v,k)=>payload[k]=v);
      try { await ajax('products_save',payload); modal.classList.add('hidden'); await renderStock(); } catch(err){ alert(err.message); }
    });
    qs('#olives-export-products')?.addEventListener('click', ()=>{
      const headers = ['Name','NAFDAC Number','Batch','Expiry','Quantity','Cost Price','Selling Price','Reorder Level'];
      const rows = state.products.map(p=>[p.name,p.nafdac_number,p.batch_number,p.expiry_date,p.quantity,p.cost_price,p.selling_price,p.reorder_level]);
      const csv = [headers,...rows].map(r=>r.map(v=>`"${String(v??'').replace(/"/g,'""')}"`).join(',')).join('\n');
      const blob = new Blob([csv],{type:'text/csv'});
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob); a.download = 'olives-products.csv'; a.click();
    });
  };

  const renderStockHistory = async () => {
    const tbody = qs('#olives-stock-history');
    if (!tbody) return;
    try {
      const data = await ajax('stock_history_list');
      const history = data.history || [];
      tbody.innerHTML = history.slice(0,10).map(h=>`<tr class="border-b"><td class="p-2">${h.product_name}</td><td class="p-2"><span class="px-2 py-1 rounded-full text-xs bg-slate-100">${h.change_type}</span></td><td class="p-2 ${Number(h.quantity_change)<0?'text-olives-red':'text-olives-green'}">${Number(h.quantity_change)>0?'+':''}${h.quantity_change}</td><td class="p-2">${h.quantity_after}</td><td class="p-2">${h.staff_id || '-'}</td><td class="p-2">${relTime(h.created_at)}</td></tr>`).join('') || '<tr><td colspan="6" class="p-2 text-slate-500">No history.</td></tr>';
      qs('#olives-stock-full-history')?.addEventListener('click',()=>alert('Full history loaded in this section (up to 500 rows via API).'));
    } catch(e){ tbody.innerHTML = `<tr><td colspan="6" class="p-2 text-olives-red">${e.message}</td></tr>`; }
  };

  const renderSalesProducts = () => {
    const wrap = qs('#olives-sales-products');
    if (!wrap) return;
    const query = (qs('#olives-sales-search')?.value || '').toLowerCase().trim();
    const list = state.products.filter(p=> p.name.toLowerCase().includes(query));
    wrap.innerHTML = list.map(p=>{
      const out = Number(p.quantity||0) <= 0;
      return `<button class="text-left p-3 rounded-[20px] border transition-all duration-200 ${out?'opacity-50 cursor-not-allowed':'hover:border-olives-green'}" ${out?'disabled':''} data-add="${p.id}"><div class="font-semibold">${p.name}</div><div class="text-sm text-slate-500">Qty: ${p.quantity}</div><div class="text-sm text-olives-green font-semibold">${fmt(p.selling_price)}</div></button>`;
    }).join('') || '<div class="col-span-full p-4 rounded-[20px] border text-center text-slate-500"><iconify-icon icon="solar:box-linear"></iconify-icon><p>No matching products.</p></div>';
    qsa('[data-add]').forEach(btn=>btn.onclick = ()=>addToCart(btn.dataset.add));
  };

  const addToCart = id => {
    const p = state.products.find(x=>String(x.id)===String(id));
    if (!p) return;
    const existing = state.cart.find(i=>i.product_id===p.id);
    if (existing) {
      if (existing.quantity < Number(p.quantity)) existing.quantity += 1;
    } else {
      state.cart.push({product_id:p.id,name:p.name,price:Number(p.selling_price),quantity:1,max:Number(p.quantity)});
    }
    renderCart();
  };

  const renderCart = () => {
    const body = qs('#olives-cart-body');
    if (!body) return;
    body.innerHTML = state.cart.map((i,idx)=>`<tr class="border-b"><td class="p-2">${i.name}</td><td class="p-2"><input data-price="${idx}" value="${fmt(i.price)}" class="w-24 rounded-[12px] border-slate-200 px-2 py-1" /></td><td class="p-2"><div class="flex items-center gap-1"><button data-qty-minus="${idx}" class="w-8 h-8 rounded-full border" aria-label="Decrease">−</button><span>${i.quantity}</span><button data-qty-plus="${idx}" class="w-8 h-8 rounded-full border" aria-label="Increase">+</button></div></td><td class="p-2">${fmt(i.price*i.quantity)}</td><td class="p-2"><button data-remove="${idx}" aria-label="Remove">✕</button></td></tr>`).join('') || '<tr><td colspan="5" class="p-3 text-center text-slate-500">Cart is empty</td></tr>';
    qsa('[data-remove]').forEach(b=>b.onclick=()=>{state.cart.splice(Number(b.dataset.remove),1);renderCart();});
    qsa('[data-qty-minus]').forEach(b=>b.onclick=()=>{const i=state.cart[Number(b.dataset.qtyMinus)]; if(i.quantity>1)i.quantity--; renderCart();});
    qsa('[data-qty-plus]').forEach(b=>b.onclick=()=>{const i=state.cart[Number(b.dataset.qtyPlus)]; if(i.quantity<i.max)i.quantity++; renderCart();});
    qsa('[data-price]').forEach(inp=>{
      inp.onfocus = ()=> inp.value = String(parseMoney(inp.value));
      inp.onblur = ()=>{
        const i=state.cart[Number(inp.dataset.price)]; i.price = parseMoney(inp.value); inp.value = fmt(i.price); renderCart();
      };
    });
    const subtotal = state.cart.reduce((t,i)=>t + (i.price*i.quantity),0);
    qs('#olives-subtotal').textContent = fmt(subtotal);
    qs('#olives-grandtotal').textContent = fmt(subtotal);
    renderSplitFields();
    const complete = qs('#olives-complete-sale');
    complete.disabled = state.cart.length === 0 || !isPaymentBalanced();
  };

  const paymentFields = () => qsa('[data-pay-field]');

  const isPaymentBalanced = () => {
    const total = state.cart.reduce((t,i)=>t+i.price*i.quantity,0);
    const fields = paymentFields();
    if (!fields.length) return total === 0 ? false : true;
    const sum = fields.reduce((s,f)=>s+parseMoney(f.value),0);
    return Math.abs(sum-total) < 1;
  };

  const renderSplitFields = () => {
    const method = qs('#olives-payment-method')?.value || 'cash';
    const box = qs('#olives-split-fields');
    if (!box) return;
    const methods = paymentMap[method] || ['cash'];
    const total = state.cart.reduce((t,i)=>t+i.price*i.quantity,0);
    if (methods.length === 1) {
      box.innerHTML = '';
      const msg = qs('#olives-balance-msg');
      if (msg) { msg.className = 'text-sm mt-2 text-olives-green'; msg.textContent = 'Balanced ✓'; }
      qs('#olives-complete-sale').disabled = state.cart.length === 0;
      return;
    }

    let defaultIdx = methods.includes('transfer') ? methods.indexOf('transfer') : 0;
    if (state.autoBalanceIndex < 0 || state.autoBalanceIndex >= methods.length) state.autoBalanceIndex = methods.length - 1;
    const vals = methods.map((_,i)=> i===defaultIdx ? total : 0);
    box.innerHTML = methods.map((m,i)=>`<label class="block"><span class="text-sm capitalize">${m} Amount</span><input data-pay-field="${m}" data-pay-index="${i}" class="w-full rounded-[20px] border-slate-200 mt-1" value="${vals[i]}" /></label>`).join('');
    paymentFields().forEach(input=>{
      input.addEventListener('input', ()=>{
        state.autoBalanceIndex = Number(input.dataset.payIndex);
        balancePayment();
      });
      input.addEventListener('focus', ()=>{
        state.autoBalanceIndex = Number(input.dataset.payIndex);
      });
    });
    if (state.autoBalanceIndex === defaultIdx) state.autoBalanceIndex = methods.length - 1;
    balancePayment();
  };

  const balancePayment = () => {
    const total = state.cart.reduce((t,i)=>t+i.price*i.quantity,0);
    const fields = paymentFields();
    if (fields.length < 2) return;
    let balancer = fields.find(f=>Number(f.dataset.payIndex)===state.autoBalanceIndex);
    if (!balancer) balancer = fields[fields.length-1];
    const sumOthers = fields.filter(f=>f!==balancer).reduce((s,f)=>s + parseMoney(f.value),0);
    const rem = Math.max(0, total - sumOthers);
    balancer.value = rem.toFixed(0);
    const sumAll = fields.reduce((s,f)=>s + parseMoney(f.value),0);
    const off = total - sumAll;
    const msg = qs('#olives-balance-msg');
    const complete = qs('#olives-complete-sale');
    if (Math.abs(off) < 1) {
      msg.className = 'text-sm mt-2 text-olives-green';
      msg.textContent = 'Balanced ✓';
      complete.disabled = state.cart.length === 0;
    } else {
      msg.className = 'text-sm mt-2 text-olives-red';
      msg.textContent = `Off by ${fmt(Math.abs(off))}`;
      complete.disabled = true;
    }
  };

  const salesPayload = () => {
    const method = qs('#olives-payment-method')?.value || 'cash';
    const methods = paymentMap[method] || ['cash'];
    const total = state.cart.reduce((t,i)=>t+i.price*i.quantity,0);
    const map = {cash_amount:0, card_amount:0, transfer_amount:0};
    if (methods.length === 1) map[methods[0] + '_amount'] = total;
    else paymentFields().forEach(f=>map[f.dataset.payField + '_amount'] = parseMoney(f.value));
    return {
      items: JSON.stringify(state.cart.map(i=>({product_id:i.product_id, quantity:i.quantity, price:i.price}))),
      payment_method: method,
      ...map
    };
  };

  const renderReceipt = receipt => {
    const cont = qs('#olives-receipt-content');
    if (!cont) return;
    const rows = (receipt.items||[]).map(i=>`<tr><td class="py-1">${i.product_name} x${i.quantity}</td><td class="py-1 text-right">${fmt(i.line_total)}</td></tr>`).join('');
    cont.innerHTML = `
      <div class="border rounded-[12px] p-3 bg-slate-50">
        <div class="text-center font-bold">${OlivesData.business?.name || 'OLIVES PHARMACY'}</div>
        <div class="text-center text-xs">${OlivesData.business?.address || ''}</div>
        <div class="text-center text-xs">${OlivesData.business?.phone || ''}</div>
        <div class="mt-2 text-xs">Receipt: ${receipt.receipt_no}<br>Date: ${receipt.created_at}<br>Staff: ${receipt.staff_name}</div>
        <table class="w-full mt-2 text-xs"><tbody>${rows}</tbody></table>
        <div class="mt-2 border-t pt-2 text-xs">
          <div class="flex justify-between"><span>Cash</span><b>${fmt(receipt.cash_amount)}</b></div>
          <div class="flex justify-between"><span>Card</span><b>${fmt(receipt.card_amount)}</b></div>
          <div class="flex justify-between"><span>Transfer</span><b>${fmt(receipt.transfer_amount)}</b></div>
          <div class="flex justify-between text-sm"><span>Total</span><b>${fmt(receipt.total_amount)}</b></div>
        </div>
        <div class="text-center mt-2 text-xs">Thank you for choosing Olives Pharmacy</div>
      </div>`;
    qs('#olives-receipt-modal')?.classList.remove('hidden');

    qs('#olives-print-receipt')?.addEventListener('click', ()=>{
      const esc = s => String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
      const lines = (receipt.items||[]).map(i=>`${esc(i.product_name).slice(0,16).padEnd(16)} ${String(i.quantity).padStart(2)} ${String(Math.round(i.line_total)).padStart(10)}`).join('\n');
      const plain = `${esc(OlivesData.business?.name || 'OLIVES PHARMACY')}\n${esc(OlivesData.business?.address || '')}\n${esc(OlivesData.business?.phone || '')}\n--------------------------------\nReceipt: ${esc(receipt.receipt_no)}\nDate: ${esc(receipt.created_at)}\nStaff: ${esc(receipt.staff_name)}\n--------------------------------\n${lines}\n--------------------------------\nCash: ${Math.round(receipt.cash_amount)}\nCard: ${Math.round(receipt.card_amount)}\nTransfer: ${Math.round(receipt.transfer_amount)}\n<b>TOTAL: ${Math.round(receipt.total_amount)}</b>\n--------------------------------\nThank you for choosing Olives Pharmacy`;
      const iframe = document.createElement('iframe');
      iframe.style.position = 'fixed'; iframe.style.right='-9999px'; iframe.style.width='58mm';
      document.body.appendChild(iframe);
      const doc = iframe.contentWindow.document;
      doc.open();
      doc.write(`<html><head><style>@page{size:58mm auto;margin:0}body{font-family:monospace;font-size:11px;padding:2mm;white-space:pre-wrap}</style></head><body>${plain}</body></html>`);
      doc.close();
      iframe.contentWindow.focus();
      iframe.contentWindow.print();
      setTimeout(()=>iframe.remove(), 1500);
    }, {once:true});
  };

  const initSales = async () => {
    await renderStock();
    renderSalesProducts();
    qs('#olives-sales-search')?.addEventListener('input', renderSalesProducts);
    qs('#olives-payment-method')?.addEventListener('change', ()=>{state.autoBalanceIndex=-1;renderSplitFields();});
    qs('#olives-complete-sale')?.addEventListener('click', async ()=>{
      if (!isPaymentBalanced()) return;
      try {
        const receipt = await ajax('sales_create', salesPayload());
        alert(OlivesData.i18n.saleSuccess);
        renderReceipt(receipt);
        state.cart = [];
        await renderStock();
        renderSalesProducts();
        renderCart();
      } catch(e){ alert(e.message); }
    });
    qs('#olives-close-receipt')?.addEventListener('click', ()=>qs('#olives-receipt-modal')?.classList.add('hidden'));
    qs('#olives-new-sale')?.addEventListener('click', ()=>{qs('#olives-receipt-modal')?.classList.add('hidden'); state.cart=[]; renderCart();});
    renderCart();
  };

  const initFinancial = async () => {
    const load = async (range='today', from='', to='') => {
      try {
        const data = await ajax('financial_summary', {range, from, to});
        const kpi = qs('#olives-financial-kpis');
        if (kpi) {
          const cards = [
            ['Total Revenue', fmt(data.totals.revenue), 'solar:wallet-money-linear'],
            ['Total Transactions', data.totals.txns, 'solar:document-text-linear'],
            ['Average Sale', fmt(data.totals.avg_sale), 'solar:chart-2-linear']
          ];
          kpi.innerHTML = cards.map(c=>`<div class="olives-card p-4"><p class="text-sm text-slate-500">${c[0]}</p><h3 class="text-2xl font-bold mt-1">${c[1]}</h3><iconify-icon class="text-2xl text-olives-green mt-2" icon="${c[2]}"></iconify-icon></div>`).join('');
        }
        const tbody = qs('#olives-financial-history');
        if (tbody) tbody.innerHTML = (data.history||[]).map(r=>`<tr class="border-b cursor-pointer" data-sale="${r.id}"><td class="p-2">${r.receipt_no}</td><td class="p-2">${r.created_at}</td><td class="p-2">${r.item_count}</td><td class="p-2"><span class="px-2 py-1 rounded-full text-xs bg-slate-100">${r.payment_method.replaceAll('_',' + ')}</span></td><td class="p-2">${fmt(r.total_amount)}</td><td class="p-2">${r.staff_name}</td></tr>`).join('') || '<tr><td colspan="6" class="p-2 text-slate-500">No transactions.</td></tr>';
        qsa('[data-sale]').forEach(tr=>tr.onclick=async()=>{
          const data = await ajax('sales_get',{sale_id:tr.dataset.sale});
          renderReceipt({...data.sale, items:data.items});
        });
      } catch(e){
        const kpi = qs('#olives-financial-kpis');
        if (kpi) kpi.innerHTML = `<p class="text-olives-red">${e.message}</p>`;
      }
    };

    qsa('#olives-financial-filters button').forEach(btn=>btn.addEventListener('click',()=>{
      qsa('#olives-financial-filters button').forEach(b=>{b.classList.remove('bg-olives-green','text-white'); b.classList.add('border');});
      btn.classList.add('bg-olives-green','text-white');
      const range = btn.dataset.range;
      qs('#olives-custom-range')?.classList.toggle('hidden', range !== 'custom');
      if (range !== 'custom') load(range);
    }));
    qs('#olives-apply-custom')?.addEventListener('click',()=>load('custom', qs('#olives-from')?.value || '', qs('#olives-to')?.value || ''));
    qs('#olives-close-receipt')?.addEventListener('click', ()=>qs('#olives-receipt-modal')?.classList.add('hidden'));
    load('today');
  };

  const initAnalytics = async () => {
    const draw = async (days=30) => {
      try {
        const data = await ajax('analytics_data',{days});
        const labels = (data.trend||[]).map(i=>i.day);
        const values = (data.trend||[]).map(i=>Number(i.total||0));
        const ctx = qs('#olives-revenue-chart');
        if (ctx) {
          if (state.chart) state.chart.destroy();
          const g = ctx.getContext('2d').createLinearGradient(0,0,0,220);
          g.addColorStop(0,'rgba(22,163,74,0.35)');
          g.addColorStop(1,'rgba(22,163,74,0.03)');
          state.chart = new Chart(ctx, {
            type:'line',
            data:{labels, datasets:[{label:'Revenue', data:values, borderColor:'#16a34a', backgroundColor:g, fill:true, tension:.35, pointRadius:3}]},
            options:{plugins:{legend:{display:false}}, scales:{x:{grid:{display:false}}, y:{grid:{color:'rgba(148,163,184,.2)'}}}
          }});
        }
        const top = qs('#olives-top-products');
        if (top) top.innerHTML = (data.top_products||[]).map((p,i)=>`<div class="p-3 rounded-[20px] border"><div class="flex justify-between"><strong>${i+1}. ${p.product_name}</strong><span>${p.qty_sold} units</span></div><p class="text-sm text-olives-green mt-1">${fmt(p.revenue)}</p></div>`).join('') || '<p class="text-sm text-slate-500">No sales data.</p>';
        const ins = qs('#olives-insights');
        if (ins) ins.innerHTML = (data.insights||[]).map(t=>`<div class="p-3 rounded-[20px] bg-slate-50 border">${t}</div>`).join('');
      } catch(e){
        qs('#olives-top-products').innerHTML = `<p class="text-olives-red">${e.message}</p>`;
      }
    };

    qsa('#olives-analytics-days button').forEach(btn=>btn.addEventListener('click',()=>{
      qsa('#olives-analytics-days button').forEach(b=>{b.classList.remove('bg-olives-green','text-white'); b.classList.add('border');});
      btn.classList.add('bg-olives-green','text-white');
      draw(Number(btn.dataset.days));
    }));

    try {
      const act = await ajax('activity_log');
      const log = qs('#olives-activity-log');
      if (log) log.innerHTML = (act.activity||[]).slice(0,15).map(a=>`<div class="p-3 rounded-[20px] bg-slate-50 border flex justify-between items-center"><div><p class="text-sm">${a.action}</p><p class="text-xs text-slate-500">${a.staff || 'System'}</p></div><span class="text-xs text-slate-500">${relTime(a.created_at)}</span></div>`).join('') || '<p class="text-sm text-slate-500">No activity yet.</p>';
    } catch(e) {}
    draw(30);
  };

  if (page === 'dashboard') renderDashboard();
  if (page === 'stock') renderStock();
  if (page === 'sales') initSales();
  if (page === 'financial') initFinancial();
  if (page === 'analytics') initAnalytics();
})();
JS;
}
}
}

Olives_Pharmacy_Plugin::instance();
register_activation_hook( __FILE__, array( 'Olives_Pharmacy_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Olives_Pharmacy_Plugin', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( 'Olives_Pharmacy_Plugin', 'uninstall' ) );
