jQuery(document).ready(function ($) {
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
                            if (item.type === 'dir') {
                                // ディレクトリの場合
                                html += '<div class="fcm-directory-file-item" data-path="' + item.path + '" data-type="' + item.type + '">';
                                html += '<div class="fcm-file-info-dir">';
                                html += '<span class="fcm-file-icon">📁</span>';
                                html += '<span class="fcm-dir-name" data-path="' + item.path + '">' + item.name + '</span>';
                                html += '</div>';
                                html += '<div class="fcm-file-actions-dir">';
                                html += '<button class="fcm-button-small fcm-button-danger fcm-delete-dir" data-dir="' + item.path + '">削除</button>';
                                html += '</div>';
                                html += '</div>';
                            } else {
                                // ファイルの場合
                                html += '<div class="fcm-directory-file-item" data-path="' + item.path + '" data-type="' + item.type + '">';
                                html += '<div class="fcm-file-info-dir">';
                                html += '<span class="fcm-file-icon">📄</span>';
                                html += '<span class="fcm-file-name-dir">' + item.name + '</span>';
                                html += '</div>';
                                html += '<div class="fcm-file-actions-dir">';
                                html += '<button class="fcm-button-small fcm-edit-file-dir" data-file="' + item.path + '">編集</button>';
                                html += '<button class="fcm-button-small fcm-button-danger fcm-delete-file-dir" data-file="' + item.path + '">削除</button>';
                                html += '</div>';
                                html += '</div>';
                            }
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

    // ディレクトリ項目全体クリック（ディレクトリ移動）
    $(document).on('click', '.fcm-directory-file-item[data-type="dir"]', function (e) {
        // ボタンがクリックされた場合は処理しない
        if ($(e.target).hasClass('fcm-button-small') || $(e.target).closest('.fcm-button-small').length > 0) {
            return;
        }

        const path = $(this).data('path');
        // ディレクトリの場合は、その階層を表示
        loadDirectory(path + '/');
        $('#file_path').val(path + '/');
    });

    // ディレクトリツリー内のファイル名クリック（パス設定用）
    $(document).on('click', '.fcm-file-name-dir', function () {
        const path = $(this).closest('.fcm-directory-file-item').data('path');
        const directory = path.substring(0, path.lastIndexOf('/') + 1);
        $('#file_path').val(directory);
    });

    // 親ディレクトリへ戻る
    $(document).on('click', '.fcm-parent-dir', function () {
        const path = $(this).data('path');
        loadDirectory(path);
        $('#file_path').val(path);
    });

    // ディレクトリツリー内のファイル編集
    $(document).on('click', '.fcm-edit-file-dir', function (e) {
        e.stopPropagation();
        e.preventDefault();

        // 一時的にrequiredフィールドを無効化
        $('#file_name').removeAttr('required');
        $('#file_path').removeAttr('required');

        const filePath = $(this).data('file');

        // 編集ページに遷移
        const editUrl = fcm_ajax_object.edit_page_url + '&file=' + encodeURIComponent(filePath);
        window.location.href = editUrl;
    });

    // ディレクトリツリー内のファイル削除
    $(document).on('click', '.fcm-delete-file-dir', function (e) {
        e.stopPropagation();

        if (!confirm('本当にこのファイルを削除しますか？')) {
            return;
        }

        const filePath = $(this).data('file');
        const $button = $(this);

        $button.prop('disabled', true).text('削除中...');

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
                    // 現在のディレクトリを再読み込み
                    const currentPath = $('#file_path').val() || fcm_ajax_object.presets.root;
                    loadDirectory(currentPath);
                } else {
                    alert('エラー: ' + response.data.message);
                    $button.prop('disabled', false).text('削除');
                }
            },
            error: function () {
                alert('削除に失敗しました');
                $button.prop('disabled', false).text('削除');
            }
        });
    });

    // ディレクトリツリー内のフォルダ削除
    $(document).on('click', '.fcm-delete-dir', function (e) {
        e.stopPropagation();

        const dirPath = $(this).data('dir');
        const dirName = dirPath.split('/').filter(Boolean).pop();

        if (!confirm('フォルダ「' + dirName + '」とその中身をすべて削除しますか？\n\n⚠️ この操作は元に戻すことができません。')) {
            return;
        }

        const $button = $(this);
        $button.prop('disabled', true).text('削除中...');

        $.ajax({
            url: fcm_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'delete_directory',
                nonce: fcm_ajax_object.nonce,
                dir_path: dirPath
            },
            success: function (response) {
                if (response.success) {
                    alert('フォルダを削除しました');
                    // 現在のディレクトリを再読み込み
                    const currentPath = $('#file_path').val() || fcm_ajax_object.presets.root;
                    loadDirectory(currentPath);
                } else {
                    alert('エラー: ' + response.data.message);
                    $button.prop('disabled', false).text('削除');
                }
            },
            error: function () {
                alert('フォルダの削除に失敗しました');
                $button.prop('disabled', false).text('削除');
            }
        });
    });

    // 初期化処理
    function initializeForm() {
        // required属性を復元
        $('#file_name').attr('required', 'required');
        $('#file_path').attr('required', 'required');
    }

    // 初期ディレクトリを読み込み
    loadDirectory(fcm_ajax_object.presets.root);

    // 初期化実行
    initializeForm();

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

        // 拡張子があるかチェック（ドットがあり、その後に文字がある場合は拡張子あり）
        const hasExtension = fileName.includes('.') && fileName.split('.').pop().length > 0 && fileName.indexOf('.') < fileName.length - 1;

        if (!hasExtension) {
            // 拡張子がない場合はフォルダを作成
            createDirectory(fileName, filePath);
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

    // フォルダ作成関数
    function createDirectory(directoryName, basePath) {
        $('#create-file-btn').prop('disabled', true).text('作成中...');

        $.ajax({
            url: fcm_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'create_directory',
                nonce: fcm_ajax_object.nonce,
                directory_name: directoryName,
                base_path: basePath
            },
            success: function (response) {
                if (response.success) {
                    $('#message-container').html(
                        '<div class="fcm-alert fcm-alert-success">' +
                        response.data.message +
                        '</div>'
                    );
                    // フォームをリセット
                    $('#file_name').val('');
                    $('#file_content').val('');
                    // ディレクトリツリーを更新
                    setTimeout(function () {
                        location.reload();
                    }, 1500);
                } else {
                    $('#message-container').html(
                        '<div class="fcm-alert fcm-alert-error">' +
                        response.data.message +
                        '</div>'
                    );
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

    // フォルダ削除
    $(document).on('click', '.fcm-delete-dir', function (e) {
        e.preventDefault();

        if (!confirm('このフォルダとその中身をすべて削除しますか？この操作は元に戻せません。')) {
            return;
        }

        const dirPath = $(this).data('dir');

        $.ajax({
            url: fcm_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'delete_directory',
                nonce: fcm_ajax_object.nonce,
                dir_path: dirPath
            },
            success: function (response) {
                if (response.success) {
                    alert('フォルダを削除しました');
                    location.reload();
                } else {
                    alert('エラー: ' + response.data.message);
                }
            },
            error: function () {
                alert('エラーが発生しました');
            }
        });
    });

    // ファイル編集
    $(document).on('click', '.fcm-edit-file', function (e) {
        e.preventDefault();

        // 一時的にrequiredフィールドを無効化
        $('#file_name').removeAttr('required');
        $('#file_path').removeAttr('required');

        const filePath = $(this).data('file');

        // 編集ページに遷移
        const editUrl = fcm_ajax_object.edit_page_url + '&file=' + encodeURIComponent(filePath);
        window.location.href = editUrl;
    });

    // 履歴クリア
    $(document).on('click', '#clear-history-btn', function (e) {
        e.preventDefault();

        if (!confirm('操作履歴をすべてクリアしますか？この操作は元に戻せません。')) {
            return;
        }

        $(this).prop('disabled', true).text('クリア中...');

        $.ajax({
            url: fcm_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'clear_history',
                nonce: fcm_ajax_object.nonce
            },
            success: function (response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('エラー: ' + response.data.message);
                }
            },
            error: function () {
                alert('エラーが発生しました');
            },
            complete: function () {
                $('#clear-history-btn').prop('disabled', false).text('履歴をクリア');
            }
        });
    });
});