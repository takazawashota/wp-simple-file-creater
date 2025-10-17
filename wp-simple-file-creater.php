<?php
/*
 * Plugin Name: WP Simple File Creator
 * Description: 管理画面から任意のファイルを作成・編集・削除できる強力なツールです。システムファイルの上書きに注意して使用してください。
 * Version: 1.0.0
 * Author: Shota Takazawa
 * Author URI: https://github.com/takazawashota/wp-simple-file-creator
 * License: GPL2
 */


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * WP Simple File Creator クラス
 */
class WP_Simple_File_Creator {
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        // 管理画面メニューを追加
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // AJAXハンドラーを登録
        add_action('wp_ajax_create_file', array($this, 'ajax_create_file'));
        add_action('wp_ajax_delete_file', array($this, 'ajax_delete_file'));
        add_action('wp_ajax_get_file_content', array($this, 'ajax_get_file_content'));
        add_action('wp_ajax_list_directory', array($this, 'ajax_list_directory'));
        add_action('wp_ajax_check_file_exists', array($this, 'ajax_check_file_exists'));
        
        // スタイルとスクリプトを登録
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // 重複ファイル記録のクリーンアップ
        add_action('admin_init', array($this, 'cleanup_duplicate_files'));
    }
    
    /**
     * 管理画面にメニューを追加
     */
    public function add_admin_menu() {
        add_menu_page(
            'WP Simple File Creator',           // ページタイトル
            'WP Simple File Creator',                       // メニュータイトル
            'manage_options',                     // 必要な権限
            'file-creator-manager',               // メニュースラッグ
            array($this, 'render_admin_page'),    // コールバック関数
            'dashicons-media-code',               // アイコン
            80                                    // メニューの位置
        );
        
        // 編集専用のサブメニューページを追加（非表示）
        add_submenu_page(
            null,                                 // 親ページ（nullで非表示）
            'ファイル編集',                        // ページタイトル
            'ファイル編集',                        // メニュータイトル
            'manage_options',                     // 必要な権限
            'file-creator-edit',                  // メニュースラッグ
            array($this, 'render_edit_page')      // コールバック関数
        );
    }
    
    /**
     * スタイルとスクリプトを読み込み
     */
    public function enqueue_admin_assets($hook) {
        // 自分のページでのみ読み込み
        if ($hook !== 'toplevel_page_file-creator-manager' && $hook !== 'admin_page_file-creator-edit') {
            return;
        }
        
        // CSSファイルを読み込み
        wp_enqueue_style(
            'fcm-admin-style',
            plugin_dir_url(__FILE__) . 'assets/css/admin.css',
            array(),
            '1.0.0'
        );
        
        // JavaScriptファイルを読み込み
        wp_enqueue_script(
            'fcm-admin-script',
            plugin_dir_url(__FILE__) . 'assets/js/admin.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        // JavaScriptにデータを渡す
        wp_localize_script('fcm-admin-script', 'fcm_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fcm_ajax_nonce'),
            'edit_page_url' => admin_url('admin.php?page=file-creator-edit'),
            'presets' => array(
                'wp-content' => WP_CONTENT_DIR . '/',
                'themes' => get_theme_root() . '/',
                'plugins' => WP_PLUGIN_DIR . '/',
                'uploads' => wp_upload_dir()['basedir'] . '/',
                'root' => ABSPATH
            )
        ));
    }
    

    

    
    /**
     * 管理画面ページのレンダリング
     */
    public function render_admin_page() {
        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        
        ?>
        <div class="wrap">
            <h1>WP Simple File Creator</h1>
            
            <div id="message-container"></div>
            
            <div class="fcm-container">
                <!-- タブ -->
                <div class="fcm-tabs">
                    <button class="fcm-tab active" data-tab="tab-create">ファイル作成</button>
                    <button class="fcm-tab" data-tab="tab-files">作成済みファイル</button>
                </div>
                
                <!-- タブ1: ファイル作成 -->
                <div id="tab-create" class="fcm-tab-content active">
                    <form id="create-file-form">
                        <!-- ファイル名 -->
                        <div class="fcm-form-group">
                            <label for="file_name">ファイル名 *</label>
                            <input 
                                type="text" 
                                id="file_name" 
                                name="file_name" 
                                placeholder="例: my-custom-file.php"
                                required
                            >
                            <p class="description">拡張子を含めてファイル名を入力してください</p>
                        </div>
                        
                        <!-- ファイルパス -->
                        <div class="fcm-form-group">
                            <label for="file_path">保存先パス *</label>
                            <div class="fcm-preset-buttons">
                                <button type="button" class="fcm-preset-btn" data-preset="wp-content">wp-content</button>
                                <button type="button" class="fcm-preset-btn" data-preset="themes">themes</button>
                                <button type="button" class="fcm-preset-btn" data-preset="plugins">plugins</button>
                                <button type="button" class="fcm-preset-btn" data-preset="uploads">uploads</button>
                                <button type="button" class="fcm-preset-btn" data-preset="root">ルート</button>
                            </div>
                            <input 
                                type="text" 
                                id="file_path" 
                                name="file_path" 
                                placeholder="<?php echo ABSPATH; ?>"
                                value="<?php echo WP_CONTENT_DIR . '/'; ?>"
                                required
                            >
                            <p class="description">ファイルを作成するディレクトリのフルパスを入力してください</p>
                        </div>
                        
                        <!-- ディレクトリツリー -->
                        <div class="fcm-form-group">
                            <label>ディレクトリ一覧（クリックでパスを設定）</label>
                            <div id="directory-tree" class="fcm-directory-tree">
                                読み込み中...
                            </div>
                        </div>
                        
                        <!-- ファイル内容 -->
                        <div class="fcm-form-group">
                            <label for="file_content">ファイル内容</label>
                            <textarea 
                                id="file_content" 
                                name="file_content" 
                                placeholder="ファイルの内容を入力してください（空でもOK）"
                            ></textarea>
                            <p class="description">PHP、HTML、CSS、JavaScript等、任意のコードを入力できます</p>
                        </div>
                        
                        <!-- 送信ボタン -->
                        <button type="submit" id="create-file-btn" class="fcm-button">
                            ファイルを作成
                        </button>
                    </form>
                </div>
                
                <!-- タブ2: 作成済みファイル -->
                <div id="tab-files" class="fcm-tab-content">
                    <h2>作成済みファイル一覧</h2>
                    <?php $this->render_file_list(); ?>
                </div>
            </div>
            
            <!-- 警告メッセージ -->
            <div class="fcm-container" style="margin-top: 20px;">
                <h3>重要な注意事項</h3>
                <ul>
                    <li>この機能は強力なため、使用には十分注意してください</li>
                    <li>システムファイルを上書きしないよう注意してください</li>
                    <li>作成前に必ずバックアップを取ることを推奨します</li>
                    <li>パーミッションエラーが発生する場合は、ディレクトリの書き込み権限を確認してください</li>
                    <li>PHPファイルを作成する場合は、構文エラーに注意してください</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * 編集ページのレンダリング
     */
    public function render_edit_page() {
        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        
        // パラメータを取得
        $file_path = isset($_GET['file']) ? wp_unslash($_GET['file']) : '';
        
        if (empty($file_path) || !file_exists($file_path)) {
            wp_die('ファイルが見つかりません');
        }
        
        // ファイル内容を取得
        $file_content = file_get_contents($file_path);
        $file_name = basename($file_path);
        $directory = dirname($file_path) . '/';
        
        ?>
        <div class="wrap">
            <h1>ファイル編集</h1>
            
            <div id="message-container"></div>
            
            <div class="fcm-container">
                <form id="edit-file-form">
                    <div class="fcm-form-group">
                        <label for="edit_file_name">ファイル名</label>
                        <input 
                            type="text" 
                            id="edit_file_name" 
                            name="file_name" 
                            value="<?php echo esc_attr($file_name); ?>"
                            readonly
                        >
                    </div>
                    
                    <div class="fcm-form-group">
                        <label for="edit_file_path">ファイルパス</label>
                        <input 
                            type="text" 
                            id="edit_file_path" 
                            name="file_path" 
                            value="<?php echo esc_attr($directory); ?>"
                            readonly
                        >
                    </div>
                    
                    <div class="fcm-form-group">
                        <label for="edit_file_content">ファイル内容</label>
                        <textarea 
                            id="edit_file_content" 
                            name="file_content"
                            placeholder="ファイルの内容を入力してください..."
                        ><?php echo esc_textarea($file_content); ?></textarea>
                    </div>
                    
                    <div class="fcm-form-group">
                        <button type="submit" id="save-file-btn" class="fcm-button">
                            ファイルを保存
                        </button>
                        <a href="<?php echo admin_url('admin.php?page=file-creator-manager'); ?>" class="fcm-button" style="margin-left: 10px; text-decoration: none; display: inline-block;">
                            一覧に戻る
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- 警告メッセージ -->
            <div class="fcm-container" style="margin-top: 20px;">
                <h3>編集時の注意事項</h3>
                <ul>
                    <li>ファイルの変更は慎重に行ってください</li>
                    <li>PHPファイルの場合、構文エラーがあるとサイトが動作しなくなる可能性があります</li>
                    <li>重要なファイルを編集する前は必ずバックアップを取ってください</li>
                </ul>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#edit-file-form').on('submit', function(e) {
                e.preventDefault();
                
                const fileName = $('#edit_file_name').val();
                const filePath = $('#edit_file_path').val();
                const fileContent = $('#edit_file_content').val();
                
                $('#save-file-btn').prop('disabled', true).text('保存中...');
                
                $.ajax({
                    url: fcm_ajax_object.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'create_file',
                        nonce: fcm_ajax_object.nonce,
                        file_name: fileName,
                        file_path: filePath,
                        file_content: fileContent,
                        force_overwrite: true
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#message-container').html(
                                '<div class="fcm-alert fcm-alert-success">' + 
                                response.data.message + 
                                '</div>'
                            );
                        } else {
                            $('#message-container').html(
                                '<div class="fcm-alert fcm-alert-error">' + 
                                response.data.message + 
                                '</div>'
                            );
                        }
                    },
                    error: function() {
                        $('#message-container').html(
                            '<div class="fcm-alert fcm-alert-error">エラーが発生しました</div>'
                        );
                    },
                    complete: function() {
                        $('#save-file-btn').prop('disabled', false).text('ファイルを保存');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * ファイル一覧を表示
     */
    private function render_file_list() {
        $files = get_option('fcm_created_files', array());
        
        if (empty($files)) {
            echo '<p>まだファイルが作成されていません</p>';
            return;
        }
        
        // 同じパスのファイルをグループ化（最新のもののみ残す）
        $grouped_files = array();
        foreach ($files as $file) {
            $full_path = $file['full_path'];
            
            // 既に同じパスのファイルが存在する場合は、より新しい日時のものを保持
            if (isset($grouped_files[$full_path])) {
                if (strtotime($file['created_at']) > strtotime($grouped_files[$full_path]['created_at'])) {
                    $grouped_files[$full_path] = $file;
                }
            } else {
                $grouped_files[$full_path] = $file;
            }
        }
        
        // 作成日時で降順ソート
        uasort($grouped_files, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        echo '<div class="fcm-file-list">';
        foreach ($grouped_files as $file) {
            $file_exists = file_exists($file['full_path']);
            ?>
            <div class="fcm-file-item">
                <div class="fcm-file-info">
                    <div class="fcm-file-name">
                        <?php echo esc_html($file['name']); ?>
                        <?php if (!$file_exists): ?>
                            <span style="color: #d63638;">（削除済み）</span>
                        <?php endif; ?>
                    </div>
                    <div class="fcm-file-path"><?php echo esc_html($file['full_path']); ?></div>
                    <small>最終更新: <?php echo esc_html($file['created_at']); ?></small>
                </div>
                <div class="fcm-file-actions">
                    <?php if ($file_exists): ?>
                        <button 
                            class="fcm-button fcm-edit-file" 
                            data-file="<?php echo esc_attr($file['full_path']); ?>"
                        >
                            編集
                        </button>
                        <button 
                            class="fcm-button fcm-button-danger fcm-delete-file" 
                            data-file="<?php echo esc_attr($file['full_path']); ?>"
                        >
                            削除
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
        echo '</div>';
    }
    
    /**
     * AJAX: ファイル作成
     */
    public function ajax_create_file() {
        // Nonceチェック
        check_ajax_referer('fcm_ajax_nonce', 'nonce');
        
        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません'));
        }
        
        $file_name = sanitize_file_name($_POST['file_name']);
        $file_path = wp_unslash($_POST['file_path']);
        $file_content = wp_unslash($_POST['file_content']);
        $force_overwrite = isset($_POST['force_overwrite']) && $_POST['force_overwrite'] === 'true';
        
        // パスの検証
        if (!$this->is_valid_path($file_path)) {
            wp_send_json_error(array('message' => '無効なパスです'));
        }
        
        // フルパスを生成
        $full_path = rtrim($file_path, '/') . '/' . $file_name;
        
        // ファイルが既に存在し、強制上書きが指定されていない場合は確認を求める
        if (file_exists($full_path) && !$force_overwrite) {
            wp_send_json_error(array(
                'message' => 'ファイルが既に存在します。上書きしますか？',
                'requires_confirmation' => true,
                'full_path' => $full_path
            ));
        }
        
        // ディレクトリが存在しない場合は作成
        $directory = dirname($full_path);
        if (!file_exists($directory)) {
            if (!wp_mkdir_p($directory)) {
                wp_send_json_error(array('message' => 'ディレクトリの作成に失敗しました'));
            }
        }
        
        // ファイルを作成
        $result = file_put_contents($full_path, $file_content);
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'ファイルの作成に失敗しました。パーミッションを確認してください。'));
        }
        
        // 作成したファイルを記録
        $this->save_created_file($file_name, $full_path);
        
        $action_message = file_exists($full_path) && $force_overwrite ? '上書き保存しました' : '作成しました';
        
        wp_send_json_success(array(
            'message' => 'ファイルを' . $action_message . ': ' . $full_path,
            'path' => $full_path
        ));
    }
    
    /**
     * AJAX: ファイル削除
     */
    public function ajax_delete_file() {
        check_ajax_referer('fcm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません'));
        }
        
        $file_path = wp_unslash($_POST['file_path']);
        
        if (!file_exists($file_path)) {
            wp_send_json_error(array('message' => 'ファイルが見つかりません'));
        }
        
        if (unlink($file_path)) {
            $this->remove_created_file($file_path);
            wp_send_json_success(array('message' => 'ファイルを削除しました'));
        } else {
            wp_send_json_error(array('message' => 'ファイルの削除に失敗しました'));
        }
    }
    
    /**
     * AJAX: ファイル内容取得
     */
    public function ajax_get_file_content() {
        check_ajax_referer('fcm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません'));
        }
        
        $file_path = wp_unslash($_POST['file_path']);
        
        if (!file_exists($file_path)) {
            wp_send_json_error(array('message' => 'ファイルが見つかりません'));
        }
        
        $content = file_get_contents($file_path);
        
        wp_send_json_success(array(
            'content' => $content,
            'filename' => basename($file_path),
            'directory' => dirname($file_path) . '/'
        ));
    }
    
    /**
     * AJAX: ディレクトリ一覧取得
     */
    public function ajax_list_directory() {
        check_ajax_referer('fcm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません'));
        }
        
        $path = wp_unslash($_POST['path']);
        
        if (!is_dir($path)) {
            wp_send_json_error(array('message' => 'ディレクトリが見つかりません'));
        }
        
        $items = array();
        $files = scandir($path);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $full_path = rtrim($path, '/') . '/' . $file;
            
            $items[] = array(
                'name' => $file,
                'path' => $full_path,
                'type' => is_dir($full_path) ? 'dir' : 'file'
            );
        }
        
        // ディレクトリを先に、ファイルを後に
        usort($items, function($a, $b) {
            if ($a['type'] === $b['type']) {
                return strcmp($a['name'], $b['name']);
            }
            return $a['type'] === 'dir' ? -1 : 1;
        });
        
        wp_send_json_success($items);
    }
    
    /**
     * AJAX: ファイル存在確認
     */
    public function ajax_check_file_exists() {
        check_ajax_referer('fcm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません'));
        }
        
        $file_name = sanitize_file_name($_POST['file_name']);
        $file_path = wp_unslash($_POST['file_path']);
        
        // パスの検証
        if (!$this->is_valid_path($file_path)) {
            wp_send_json_error(array('message' => '無効なパスです'));
        }
        
        // フルパスを生成
        $full_path = rtrim($file_path, '/') . '/' . $file_name;
        
        $file_exists = file_exists($full_path);
        
        wp_send_json_success(array(
            'exists' => $file_exists,
            'full_path' => $full_path
        ));
    }
    
    /**
     * パスの検証
     */
    private function is_valid_path($path) {
        // ABSPATH内のパスのみ許可
        $real_path = realpath($path);
        $abspath = realpath(ABSPATH);
        
        // パスがABSPATH内にあるか確認
        if ($real_path === false) {
            // ディレクトリが存在しない場合は親ディレクトリをチェック
            $parent = dirname($path);
            $real_parent = realpath($parent);
            if ($real_parent === false || strpos($real_parent, $abspath) !== 0) {
                return false;
            }
        } else {
            if (strpos($real_path, $abspath) !== 0) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 作成したファイルを記録
     */
    private function save_created_file($file_name, $full_path) {
        $files = get_option('fcm_created_files', array());
        
        // 既存の同じパスのファイル記録を削除
        $files = array_filter($files, function($file) use ($full_path) {
            return $file['full_path'] !== $full_path;
        });
        
        // 新しい記録を追加
        $files[] = array(
            'name' => $file_name,
            'full_path' => $full_path,
            'created_at' => current_time('mysql')
        );
        
        update_option('fcm_created_files', $files);
    }
    
    /**
     * 記録からファイルを削除
     */
    private function remove_created_file($full_path) {
        $files = get_option('fcm_created_files', array());
        
        $files = array_filter($files, function($file) use ($full_path) {
            return $file['full_path'] !== $full_path;
        });
        
        update_option('fcm_created_files', array_values($files));
    }
    
    /**
     * 重複したファイル記録をクリーンアップ
     */
    public function cleanup_duplicate_files() {
        $files = get_option('fcm_created_files', array());
        
        if (empty($files)) {
            return;
        }
        
        $grouped_files = array();
        foreach ($files as $file) {
            $full_path = $file['full_path'];
            
            // 既に同じパスのファイルが存在する場合は、より新しい日時のものを保持
            if (isset($grouped_files[$full_path])) {
                if (strtotime($file['created_at']) > strtotime($grouped_files[$full_path]['created_at'])) {
                    $grouped_files[$full_path] = $file;
                }
            } else {
                $grouped_files[$full_path] = $file;
            }
        }
        
        // クリーンアップされたデータを保存
        update_option('fcm_created_files', array_values($grouped_files));
    }
}

// プラグインを初期化
new WP_Simple_File_Creator();
