jQuery(document).ready(function ($) {
    // タブ切り替え
    $('.fcm-tab').on('click', function () {
        const tabId = $(this).data('tab');
        $('.fcm-tab').removeClass('active');
        $('.fcm-tab-content').removeClass('active');
        $(this).addClass('active');
        $('#' + tabId).addClass('active');
    });

    // プリセットパス（PHPからローカライズされた値を使用）
    const presets = fcm_ajax_object.presets;

    // プリセットボタンのクリック
    $('.fcm-preset-btn').on('click', function () {
        const preset = $(this).data('preset');
        const path = presets[preset];
        $('#file_path').val(path);
        // クリックした階層のディレクトリを表示
        loadDirectory(path);
    });

    // ディレクトリ一覧を読み込み
    function loadDirectory(path) {
        $('#directory-tree').html('<div style="padding: 10px; color: #646970;">読み込み中...</div>');

        $.ajax({
            url: fcm_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'list_directory',
                nonce: fcm_ajax_object.nonce,
                path: path
            },
            success: function (response) {
                if (response.success) {
                    let html = '';

                    // 親ディレクトリへ戻るボタン（ルートでない場合）
                    if (path !== fcm_ajax_object.presets.root && path !== '/') {
                        const parentPath = path.substring(0, path.lastIndexOf('/', path.length - 2) + 1);
                        html += '<div class="fcm-directory-item fcm-parent-dir" data-path="' + parentPath + '" style="font-weight: bold; color: #2271b1;">';
                        html += '親ディレクトリへ戻る</div>';
                        html += '<hr style="margin: 10px 0; border: none; border-top: 1px solid #ddd;">';
                    }

                    // 現在のパスを表示
                    html += '<div style="padding: 5px; background: #f0f0f1; margin-bottom: 10px; font-size: 12px; color: #646970;">';
                    html += '📂 現在: ' + path + '</div>';

                    if (response.data.length === 0) {
                        html += '<div style="padding: 10px; color: #646970;">このディレクトリは空です</div>';
                    } else {
                        response.data.forEach(function (item) {
                            const itemClass = item.type === 'dir' ? 'fcm-directory-item fcm-dir-item' : 'fcm-directory-item fcm-file-item';
                            html += '<div class="' + itemClass + '" data-path="' + item.path + '" data-type="' + item.type + '">';
                            html += item.type === 'dir' ? '📁 ' : '📄 ';
                            html += item.name + '</div>';
                        });
                    }
                    $('#directory-tree').html(html);
                } else {
                    $('#directory-tree').html('<div style="padding: 10px; color: #d63638;">エラー: ' + response.data.message + '</div>');
                }
            },
            error: function () {
                $('#directory-tree').html('<div style="padding: 10px; color: #d63638;">読み込みに失敗しました</div>');
            }
        });
    }

    // ディレクトリアイテムのクリック
    $(document).on('click', '.fcm-dir-item', function () {
        const path = $(this).data('path');
        // ディレクトリの場合は、その階層を表示
        loadDirectory(path + '/');
        $('#file_path').val(path + '/');
    });

    // ファイルアイテムのクリック
    $(document).on('click', '.fcm-file-item', function () {
        const path = $(this).data('path');
        const directory = path.substring(0, path.lastIndexOf('/') + 1);
        $('#file_path').val(directory);
    });

    // 親ディレクトリへ戻る
    $(document).on('click', '.fcm-parent-dir', function () {
        const path = $(this).data('path');
        loadDirectory(path);
        $('#file_path').val(path);
    });

    // 初期ディレクトリを読み込み
    loadDirectory(fcm_ajax_object.presets.root);

    // ファイル作成フォームの送信
    $('#create-file-form').on('submit', function (e) {
        e.preventDefault();

        const fileName = $('#file_name').val();
        const filePath = $('#file_path').val();
        const fileContent = $('#file_content').val();

        if (!fileName || !filePath) {
            alert('ファイル名とパスを入力してください');
            return;
        }

        // ファイル作成を実行する関数
        function createFile(forceOverwrite = false) {
            $('#create-file-btn').prop('disabled', true).text('作成中...');

            $.ajax({
                url: fcm_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'create_file',
                    nonce: fcm_ajax_object.nonce,
                    file_name: fileName,
                    file_path: filePath,
                    file_content: fileContent,
                    force_overwrite: forceOverwrite
                },
                success: function (response) {
                    if (response.success) {
                        $('#message-container').html(
                            '<div class="fcm-alert fcm-alert-success">' +
                            response.data.message +
                            '</div>'
                        );
                        // フォームをリセット
                        $('#file_content').val('');
                        // ファイルリストを更新
                        setTimeout(function () {
                            location.reload();
                        }, 1500);
                    } else {
                        // 上書き確認が必要な場合
                        if (response.data.requires_confirmation) {
                            const confirmMessage = response.data.message + '\n\nファイルパス: ' + response.data.full_path;
                            if (confirm(confirmMessage)) {
                                // 上書きを許可して再実行
                                createFile(true);
                                return; // ここでreturnして、下のcompleteを実行しない
                            }
                        } else {
                            $('#message-container').html(
                                '<div class="fcm-alert fcm-alert-error">' +
                                response.data.message +
                                '</div>'
                            );
                        }
                    }
                },
                error: function () {
                    $('#message-container').html(
                        '<div class="fcm-alert fcm-alert-error">エラーが発生しました</div>'
                    );
                },
                complete: function () {
                    $('#create-file-btn').prop('disabled', false).text('ファイルを作成');
                }
            });
        }

        // 初回実行
        createFile(false);
    });

    // ファイル削除
    $(document).on('click', '.fcm-delete-file', function () {
        if (!confirm('本当にこのファイルを削除しますか？')) {
            return;
        }

        const filePath = $(this).data('file');

        $.ajax({
            url: fcm_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'delete_file',
                nonce: fcm_ajax_object.nonce,
                file_path: filePath
            },
            success: function (response) {
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
    $(document).on('click', '.fcm-edit-file', function () {
        const filePath = $(this).data('file');

        // 編集ページに遷移
        const editUrl = fcm_ajax_object.edit_page_url + '&file=' + encodeURIComponent(filePath);
        window.location.href = editUrl;
    });
});