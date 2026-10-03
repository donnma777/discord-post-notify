<?php
/**
 * Plugin Name: Discord Post Notify
 * Description: 新規投稿の公開・Contact Form 7の送信・コメント投稿時にDiscordのWebhookへ通知を送信します。
 * Version: 2.0.0
 * Author: donnma
 * Author URI: https://donnma.com/
 * Plugin URI: https://github.com/donnma777/discord-post-notify
 * License: GPLv2 or later
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DPN_DISCORD_OPTION_KEY', 'dpn_discord_webhook_url' );

/**
 * 用途ごとのWebhook URLオプションキー。
 * 各項目は複数URL(改行区切り)を保持でき、空であれば DPN_DISCORD_OPTION_KEY(デフォルト)にフォールバックする。
 */
function dpn_discord_channel_option_keys() {
	return array(
		'post'    => 'dpn_discord_webhook_url_post',
		'cf7'     => 'dpn_discord_webhook_url_cf7',
		'comment' => 'dpn_discord_webhook_url_comment',
	);
}

/**
 * 設定画面(設定 > Discord通知)を追加する。
 */
function dpn_discord_add_settings_page() {
	add_options_page(
		'Discord通知設定',
		'Discord通知',
		'manage_options',
		'discord-post-notify',
		'dpn_discord_render_settings_page'
	);
}
add_action( 'admin_menu', 'dpn_discord_add_settings_page' );

/**
 * プラグイン一覧に「設定」のリンクを出す。
 */
function dpn_discord_action_links( $links ) {
	if ( current_user_can( 'manage_options' ) ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=discord-post-notify' ) ) . '">設定</a>' );
	}
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'dpn_discord_action_links' );

/**
 * 改行区切りで複数のWebhook URLを保存するためのサニタイズ処理。
 * 各行をesc_url_rawで検証し、空行は除去して"\n"で結合し直す。
 */
function dpn_discord_sanitize_webhook_urls( $raw ) {
	$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
	$urls  = array();
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( $line === '' ) {
			continue;
		}
		$escaped = esc_url_raw( $line );
		if ( $escaped ) {
			$urls[] = $escaped;
		}
	}
	return implode( "\n", $urls );
}

/**
 * 設定項目を登録する。
 */
function dpn_discord_register_settings() {
	$args = array(
		'type'              => 'string',
		'sanitize_callback' => 'dpn_discord_sanitize_webhook_urls',
		'default'           => '',
	);

	register_setting( 'dpn_discord_settings_group', DPN_DISCORD_OPTION_KEY, $args );

	foreach ( dpn_discord_channel_option_keys() as $option_key ) {
		register_setting( 'dpn_discord_settings_group', $option_key, $args );
	}
}
add_action( 'admin_init', 'dpn_discord_register_settings' );

/**
 * 保存されているオプション値(改行区切り)をURLの配列にする。
 */
function dpn_discord_option_to_urls( $option_key ) {
	$raw = get_option( $option_key, '' );
	if ( $raw === '' ) {
		return array();
	}
	$lines = preg_split( '/\r\n|\r|\n/', $raw );
	return array_values( array_filter( array_map( 'trim', $lines ) ) );
}

/**
 * 設定画面を出力する。
 */
function dpn_discord_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$channel_labels = array(
		'post'    => '新規投稿公開',
		'cf7'     => 'お問い合わせフォーム送信',
		'comment' => 'コメント投稿',
	);
	?>
	<div class="wrap">
		<h1>Discord通知設定</h1>
		<p>新規投稿の公開時、Contact Form 7の送信時、コメント投稿時に通知を送るDiscordのWebhook URLを設定します。<br />
		1行に1つずつ入力すると、複数のDiscordサーバー/チャンネルへ同時に通知できます。<br />
		用途別URLを空欄にすると、その用途はデフォルトURLへ通知されます。</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'dpn_discord_settings_group' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="<?php echo esc_attr( DPN_DISCORD_OPTION_KEY ); ?>">デフォルトWebhook URL</label>
					</th>
					<td>
						<textarea
							id="<?php echo esc_attr( DPN_DISCORD_OPTION_KEY ); ?>"
							name="<?php echo esc_attr( DPN_DISCORD_OPTION_KEY ); ?>"
							class="large-text code"
							rows="3"
							placeholder="https://discord.com/api/webhooks/xxxx/yyyy&#10;https://discord.com/api/webhooks/zzzz/wwww"
						><?php echo esc_textarea( get_option( DPN_DISCORD_OPTION_KEY, '' ) ); ?></textarea>
						<p class="description">用途別URLが未設定の場合にここへ通知されます。1行1URLで複数指定可能。Discordのチャンネル設定 → 連携サービス → ウェブフック から発行できます。</p>
					</td>
				</tr>
				<?php foreach ( dpn_discord_channel_option_keys() as $channel => $option_key ) : ?>
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( $option_key ); ?>"><?php echo esc_html( $channel_labels[ $channel ] ); ?>用URL</label>
						</th>
						<td>
							<textarea
								id="<?php echo esc_attr( $option_key ); ?>"
								name="<?php echo esc_attr( $option_key ); ?>"
								class="large-text code"
								rows="3"
								placeholder="未設定時はデフォルトURLを使用(1行1URLで複数指定可能)"
							><?php echo esc_textarea( get_option( $option_key, '' ) ); ?></textarea>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button( '保存' ); ?>
		</form>
		<?php if ( get_option( DPN_DISCORD_OPTION_KEY, '' ) ) : ?>
			<hr />
			<h2>テスト送信</h2>
			<p>デフォルトURL(1行1URLすべて)へテストメッセージを送信します。</p>
			<form method="post">
				<?php wp_nonce_field( 'dpn_discord_test_send' ); ?>
				<input type="hidden" name="dpn_discord_test" value="1" />
				<?php submit_button( 'テストメッセージを送信', 'secondary' ); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * テスト送信ボタンの処理。
 */
function dpn_discord_handle_test_send() {
	if ( empty( $_POST['dpn_discord_test'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'dpn_discord_test_send' );

	if ( ! empty( dpn_discord_get_webhook_urls() ) ) {
		dpn_discord_send_message( sprintf( '%s: テスト通知です。この設定は正しく動作しています。', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) );
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-success"><p>テストメッセージを送信しました。Discordを確認してください。</p></div>';
			}
		);
	}
}
add_action( 'admin_init', 'dpn_discord_handle_test_send' );

/**
 * Webhook URLの配列を取得する。
 * $channel を指定すると用途別URL('post'/'cf7'/'comment')を優先し、未設定ならデフォルトURLにフォールバックする。
 * wp-config.phpでDISCORD_WEBHOOK_URLが定義されている場合は、全用途に対して常にそちらを最優先する(上級者向け)。
 */
function dpn_discord_get_webhook_urls( $channel = '' ) {
	if ( defined( 'DISCORD_WEBHOOK_URL' ) && DISCORD_WEBHOOK_URL !== '' ) {
		return array( DISCORD_WEBHOOK_URL );
	}

	$channel_keys = dpn_discord_channel_option_keys();
	if ( $channel !== '' && isset( $channel_keys[ $channel ] ) ) {
		$channel_urls = dpn_discord_option_to_urls( $channel_keys[ $channel ] );
		if ( ! empty( $channel_urls ) ) {
			return $channel_urls;
		}
	}

	return dpn_discord_option_to_urls( DPN_DISCORD_OPTION_KEY );
}

/**
 * Discordへメッセージを送信する共通処理。対象URLすべてに同じ内容を送る。
 * Discordの1メッセージあたりの文字数上限(2000文字)を考慮して安全に切り詰める。
 */
function dpn_discord_send_message( $message, $channel = '' ) {
	$urls = dpn_discord_get_webhook_urls( $channel );
	if ( empty( $urls ) ) {
		return;
	}

	if ( function_exists( 'mb_strlen' ) && mb_strlen( $message ) > 1900 ) {
		$message = mb_substr( $message, 0, 1900 ) . "\n…(省略)";
	}

	foreach ( $urls as $url ) {
		wp_remote_post(
			$url,
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'content'          => $message,
						// 本文に @everyone / @here / <@id> があっても通知しない(フォームやコメントは外部の人が書けるため)
						'allowed_mentions' => array( 'parse' => array() ),
					)
				),
				'timeout' => 10,
			)
		);
	}
}

/**
 * 投稿が「公開」状態に遷移したときにDiscordへ通知する。
 */
function dpn_notify_discord_on_publish( $new_status, $old_status, $post ) {
	// 「公開されていない」→「公開」への遷移のときだけ実行(更新時の再送信を防ぐ)
	if ( $new_status !== 'publish' || $old_status === 'publish' ) {
		return;
	}

	// 通常の投稿(post)のみ対象。固定ページ等も通知したい場合はここを調整する。
	if ( $post->post_type !== 'post' ) {
		return;
	}

	$title = get_the_title( $post );
	$url   = get_permalink( $post );

	dpn_discord_send_message( sprintf( "新しい記事が公開されました！\n**%s**\n%s", $title, $url ), 'post' );
}
add_action( 'transition_post_status', 'dpn_notify_discord_on_publish', 10, 3 );

/**
 * Contact Form 7 の送信完了時にDiscordへ通知する。
 * メールが正常に送信された場合のみ発火する(スパム判定等で失敗した場合は送らない)。
 */
function dpn_notify_discord_on_cf7_submit( $contact_form ) {
	if ( empty( dpn_discord_get_webhook_urls( 'cf7' ) ) ) {
		return;
	}

	if ( ! class_exists( 'WPCF7_Submission' ) ) {
		return;
	}

	$submission = WPCF7_Submission::get_instance();
	if ( ! $submission ) {
		return;
	}

	$posted_data = $submission->get_posted_data();
	$lines       = array();
	foreach ( $posted_data as $key => $value ) {
		// "_wpcf7"等の内部メタ項目は除外し、実際にユーザーが入力した項目のみ通知する。
		if ( strpos( $key, '_' ) === 0 ) {
			continue;
		}
		if ( is_array( $value ) ) {
			$value = implode( ', ', $value );
		}
		$lines[] = sprintf( '**%s**: %s', $key, $value );
	}

	$message = sprintf(
		"お問い合わせフォームが送信されました。(%s)\n%s",
		$contact_form->title(),
		implode( "\n", $lines )
	);

	dpn_discord_send_message( $message, 'cf7' );
}
add_action( 'wpcf7_mail_sent', 'dpn_notify_discord_on_cf7_submit' );

/**
 * コメントが投稿されたときにDiscordへ通知する。
 * スパム判定されたコメントは対象外(Akismet等が comment_approved を 'spam' にした場合)。
 * 承認待ち/承認済みのどちらでも通知し、状態が分かるようメッセージに含める。
 */
function dpn_notify_discord_on_comment( $comment_id, $comment ) {
	if ( empty( dpn_discord_get_webhook_urls( 'comment' ) ) ) {
		return;
	}

	if ( $comment->comment_approved === 'spam' || $comment->comment_approved === 'trash' ) {
		return;
	}

	$post = get_post( $comment->comment_post_ID );
	if ( ! $post ) {
		return;
	}

	$status  = ( $comment->comment_approved === '1' ) ? '承認済み' : '承認待ち';
	$excerpt = wp_strip_all_tags( $comment->comment_content );

	$message = sprintf(
		"新しいコメントが投稿されました。(%s)\n投稿: %s\n投稿者: %s\n%s\n\n%s",
		$status,
		get_the_title( $post ),
		$comment->comment_author,
		get_comment_link( $comment ),
		$excerpt
	);

	dpn_discord_send_message( $message, 'comment' );
}
add_action( 'wp_insert_comment', 'dpn_notify_discord_on_comment', 10, 2 );
