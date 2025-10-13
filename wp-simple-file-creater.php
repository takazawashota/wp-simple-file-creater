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
 * ファイル作成マネージャークラス
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
        
        // スタイルとスクリプトを登録
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * 管理画面にメニューを追加
     */
    public function add_admin_menu() {
        add_menu_page(
            'ファイル作成マネージャー',           // ページタイトル
            'ファイル作成',                       // メニュータイトル
            'manage_options',                     // 必要な権限
            'file-creator-manager',               // メニュースラッグ
            array($this, 'render_admin_page'),    // コールバック関数
            'dashicons-media-code',               // アイコン
            80                                    // メニューの位置
        );
    }
    
    /**
     * スタイルとスクリプトを読み込み
     */
    public function enqueue_admin_assets($hook) {
        // 自分のページでのみ読み込み
        if ($hook !== 'toplevel_page_file-creator-manager') {
            return;
        }
        
        // インラインスタイル
        wp_add_inline_style('wp-admin', $this->get_admin_styles());
        
        // インラインスクリプト
        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery', $this->get_admin_scripts());
    }
    
    /**
     * 管理画面のスタイル
     */
    private function get_admin_styles() {
        return "
        .fcm-container {
            max-width: 1200px;
            margin: 20px 0;
            background: #fff;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .fcm-form-group {
            margin-bottom: 20px;
        }
        .fcm-form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1d2327;
        }
        .fcm-form-group input[type='text'],
        .fcm-form-group select,
        .fcm-form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .fcm-form-group textarea {
            min-height: 300px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .fcm-button {
            background: #2271b1;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        .fcm-button:hover {
            background: #135e96;
        }
        .fcm-button-danger {
            background: #d63638;
        }
        .fcm-button-danger:hover {
            background: #b32d2e;
        }
        .fcm-alert {
            padding: 12px 16px;
            margin: 20px 0;
            border-radius: 4px;
            border-left: 4px solid;
        }
        .fcm-alert-success {
            background: #edfaef;
            border-color: #00a32a;
            color: #00600f;
        }
        .fcm-alert-error {
            background: #fcf0f1;
            border-color: #d63638;
            color: #50575e;
        }
        .fcm-file-list {
            margin-top: 30px;
        }
        .fcm-file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
            background: #f9f9f9;
        }
        .fcm-file-info {
            flex: 1;
        }
        .fcm-file-name {
            font-weight: 600;
            color: #1d2327;
        }
        .fcm-file-path {
            font-size: 12px;
            color: #646970;
            font-family: 'Courier New', monospace;
        }
        .fcm-file-actions {
            display: flex;
            gap: 10px;
        }
        .fcm-preset-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .fcm-preset-btn {
            padding: 6px 12px;
            background: #f0f0f1;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }
        .fcm-preset-btn:hover {
            background: #dcdcde;
        }
        .fcm-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }
        .fcm-tab {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 14px;
            font-weight: 500;
            color: #646970;
        }
        .fcm-tab.active {
            color: #2271b1;
            border-bottom: 2px solid #2271b1;
            margin-bottom: -2px;
        }
        .fcm-tab-content {
            display: none;
        }
        .fcm-tab-content.active {
            display: block;
        }
        .fcm-directory-tree {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            background: #f9f9f9;
        }
        .fcm-directory-item {
            padding: 5px;
            cursor: pointer;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            border-radius: 3px;
            transition: background 0.2s;
        }
        .fcm-directory-item:hover {
            background: #e0e0e0;
        }
        .fcm-dir-item {
            font-weight: 500;
            color: #2271b1;
        }
        .fcm-file-item {
            color: #646970;
        }
        .fcm-parent-dir {
            border-bottom: 1px solid #ddd;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        ";
    }
    
    /**
     * 管理画面のJavaScript
     */
    private function get_admin_scripts() {
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('fcm_ajax_nonce');
        
        return "
        jQuery(document).ready(function($) {
            // タブ切り替え
            $('.fcm-tab').on('click', function() {
                const tabId = $(this).data('tab');
                $('.fcm-tab').removeClass('active');
                $('.fcm-tab-content').removeClass('active');
                $(this).addClass('active');
                $('#' + tabId).addClass('active');
            });
            
            // プリセットパス
            const presets = {
                'wp-content': '" . WP_CONTENT_DIR . "/',
                'themes': '" . get_theme_root() . "/',
                'plugins': '" . WP_PLUGIN_DIR . "/',
                'uploads': '" . wp_upload_dir()['basedir'] . "/',
                'root': '" . ABSPATH . "'
            };
            
            // プリセットボタンのクリック
            $('.fcm-preset-btn').on('click', function() {
                const preset = $(this).data('preset');
                const path = presets[preset];
                $('#file_path').val(path);
                // クリックした階層のディレクトリを表示
                loadDirectory(path);
            });
            
            // ディレクトリ一覧を読み込み
            function loadDirectory(path) {
                $('#directory-tree').html('<div style=\"padding: 10px; color: #646970;\">読み込み中...</div>');
                
                $.ajax({
                    url: '" . $ajax_url . "',
                    type: 'POST',
                    data: {
                        action: 'list_directory',
                        nonce: '" . $nonce . "',
                        path: path
                    },
                    success: function(response) {
                        if (response.success) {
                            let html = '';
                            
                            // 親ディレクトリへ戻るボタン（ルートでない場合）
                            if (path !== '" . ABSPATH . "' && path !== '/') {
                                const parentPath = path.substring(0, path.lastIndexOf('/', path.length - 2) + 1);
                                html += '<div class=\"fcm-directory-item fcm-parent-dir\" data-path=\"' + parentPath + '\" style=\"font-weight: bold; color: #2271b1;\">';
                                html += '⬆️ 親ディレクトリへ戻る</div>';
                                html += '<hr style=\"margin: 10px 0; border: none; border-top: 1px solid #ddd;\">';
                            }
                            
                            // 現在のパスを表示
                            html += '<div style=\"padding: 5px; background: #f0f0f1; margin-bottom: 10px; font-size: 12px; font-family: Courier New; color: #646970;\">';
                            html += '📂 現在: ' + path + '</div>';
                            
                            if (response.data.length === 0) {
                                html += '<div style=\"padding: 10px; color: #646970;\">このディレクトリは空です</div>';
                            } else {
                                response.data.forEach(function(item) {
                                    const itemClass = item.type === 'dir' ? 'fcm-directory-item fcm-dir-item' : 'fcm-directory-item fcm-file-item';
                                    html += '<div class=\"' + itemClass + '\" data-path=\"' + item.path + '\" data-type=\"' + item.type + '\">';
                                    html += item.type === 'dir' ? '📁 ' : '📄 ';
                                    html += item.name + '</div>';
                                });
                            }
                            $('#directory-tree').html(html);
                        } else {
                            $('#directory-tree').html('<div style=\"padding: 10px; color: #d63638;\">エラー: ' + response.data.message + '</div>');
                        }
                    },
                    error: function() {
                        $('#directory-tree').html('<div style=\"padding: 10px; color: #d63638;\">読み込みに失敗しました</div>');
                    }
                });
            }
            
            // ディレクトリアイテムのクリック
            $(document).on('click', '.fcm-dir-item', function() {
                const path = $(this).data('path');
                // ディレクトリの場合は、その階層を表示
                loadDirectory(path + '/');
                $('#file_path').val(path + '/');
            });
            
            // ファイルアイテムのクリック
            $(document).on('click', '.fcm-file-item', function() {
                const path = $(this).data('path');
                const directory = path.substring(0, path.lastIndexOf('/') + 1);
                $('#file_path').val(directory);
            });
            
            // 親ディレクトリへ戻る
            $(document).on('click', '.fcm-parent-dir', function() {
                const path = $(this).data('path');
                loadDirectory(path);
                $('#file_path').val(path);
            });
            
            // 初期ディレクトリを読み込み
            loadDirectory('" . ABSPATH . "');
            
            // ファイル作成フォームの送信
            $('#create-file-form').on('submit', function(e) {
                e.preventDefault();
                
                const fileName = $('#file_name').val();
                const filePath = $('#file_path').val();
                const fileContent = $('#file_content').val();
                
                if (!fileName || !filePath) {
                    alert('ファイル名とパスを入力してください');
                    return;
                }
                
                $('#create-file-btn').prop('disabled', true).text('作成中...');
                
                $.ajax({
                    url: '" . $ajax_url . "',
                    type: 'POST',
                    data: {
                        action: 'create_file',
                        nonce: '" . $nonce . "',
                        file_name: fileName,
                        file_path: filePath,
                        file_content: fileContent
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#message-container').html(
                                '<div class=\"fcm-alert fcm-alert-success\">' + 
                                response.data.message + 
                                '</div>'
                            );
                            // フォームをリセット
                            $('#file_content').val('');
                            // ファイルリストを更新
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            $('#message-container').html(
                                '<div class=\"fcm-alert fcm-alert-error\">' + 
                                response.data.message + 
                                '</div>'
                            );
                        }
                    },
                    error: function() {
                        $('#message-container').html(
                            '<div class=\"fcm-alert fcm-alert-error\">エラーが発生しました</div>'
                        );
                    },
                    complete: function() {
                        $('#create-file-btn').prop('disabled', false).text('ファイルを作成');
                    }
                });
            });
            
            // ファイル削除
            $(document).on('click', '.fcm-delete-file', function() {
                if (!confirm('本当にこのファイルを削除しますか？')) {
                    return;
                }
                
                const filePath = $(this).data('file');
                
                $.ajax({
                    url: '" . $ajax_url . "',
                    type: 'POST',
                    data: {
                        action: 'delete_file',
                        nonce: '" . $nonce . "',
                        file_path: filePath
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('ファイルを削除しました');
                            location.reload();
                        } else {
                            alert('エラー: ' + response.data.message);
                        }
                    }
                });
            });
            
            // ファイル編集
            $(document).on('click', '.fcm-edit-file', function() {
                const filePath = $(this).data('file');
                
                $.ajax({
                    url: '" . $ajax_url . "',
                    type: 'POST',
                    data: {
                        action: 'get_file_content',
                        nonce: '" . $nonce . "',
                        file_path: filePath
                    },
                    success: function(response) {
                        if (response.success) {
                            // タブを「ファイル作成」に切り替え
                            $('.fcm-tab[data-tab=\"tab-create\"]').click();
                            
                            // フォームに値を設定
                            $('#file_content').val(response.data.content);
                            $('#file_name').val(response.data.filename);
                            $('#file_path').val(response.data.directory);
                            
                            // ディレクトリツリーも更新
                            loadDirectory(response.data.directory);
                            
                            // 上部にスクロール
                            $('html, body').animate({ scrollTop: 0 }, 'slow');
                            
                            // 編集中であることを通知
                            $('#message-container').html(
                                '<div class=\"fcm-alert fcm-alert-success\">' + 
                                '📝 ファイル「' + response.data.filename + '」を編集中です。変更後「ファイルを作成」ボタンで上書き保存されます。' +
                                '</div>'
                            );
                        } else {
                            alert('エラー: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('ファイルの読み込みに失敗しました');
                    }
                });
            });
        });
        ";
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
            <h1>📝 ファイル作成マネージャー</h1>
            
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
                <h3>⚠️ 重要な注意事項</h3>
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
     * ファイル一覧を表示
     */
    private function render_file_list() {
        $files = get_option('fcm_created_files', array());
        
        if (empty($files)) {
            echo '<p>まだファイルが作成されていません</p>';
            return;
        }
        
        echo '<div class="fcm-file-list">';
        foreach ($files as $file) {
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
                    <small>作成日時: <?php echo esc_html($file['created_at']); ?></small>
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
        
        // パスの検証
        if (!$this->is_valid_path($file_path)) {
            wp_send_json_error(array('message' => '無効なパスです'));
        }
        
        // フルパスを生成
        $full_path = rtrim($file_path, '/') . '/' . $file_name;
        
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
        
        wp_send_json_success(array(
            'message' => 'ファイルを作成しました: ' . $full_path,
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
}

// プラグインを初期化
new WP_Simple_File_Creator();
