<?php
/**
 * Plugin Name: Discord Post Notify
 * Description: 新規投稿の公開・フォームの送信・コメント投稿・ログイン時にDiscordのWebhookへ通知を送信します。
 * Version: 3.1.1
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

// 記事の公開から通知を送るまでの秒数(WP-Cron なので、この時間のあとの最初のアクセスで送られる)
define( 'DPN_PUBLISH_NOTICE_DELAY', 60 );

/**
 * 用途ごとのWebhook URLオプションキー。
 * 各項目は複数URL(改行区切り)を保持でき、空であれば DPN_DISCORD_OPTION_KEY(デフォルト)にフォールバックする。
 * 'cf7' はフォームの送信に使う(Contact Form 7 用だったころの設定を引き継ぐため名前はそのまま)。
 * 'login' だけはフォールバックしない(IPが載るので、入れたときだけ送る)。
 */
function dpn_discord_channel_option_keys() {
	return array(
		'post'    => 'dpn_discord_webhook_url_post',
		'cf7'     => 'dpn_discord_webhook_url_cf7',
		'comment' => 'dpn_discord_webhook_url_comment',
		'login'   => 'dpn_discord_webhook_url_login',
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
 * 設定画面の欄の名前('' はデフォルト)。
 */
function dpn_discord_channel_labels() {
	return array(
		''        => 'デフォルト',
		'post'    => '新規投稿公開',
		'cf7'     => 'フォーム送信',
		'comment' => 'コメント投稿',
		'login'   => 'ログイン',
	);
}

/**
 * 欄のオプションキー('' はデフォルト)。
 */
function dpn_discord_channel_option_key( $channel ) {
	$keys = dpn_discord_channel_option_keys();
	return '' === $channel ? DPN_DISCORD_OPTION_KEY : $keys[ $channel ];
}

/**
 * その欄にURLが保存してあれば「この欄にテスト送信」を出す。
 */
function dpn_discord_render_test_button( $channel ) {
	if ( empty( dpn_discord_option_to_urls( dpn_discord_channel_option_key( $channel ) ) ) ) {
		return;
	}
	printf(
		'<p><button type="submit" form="dpn-discord-test" name="dpn_discord_channel" value="%s" class="button button-small">この欄にテスト送信</button></p>',
		esc_attr( $channel )
	);
}

/**
 * 設定画面を出力する。
 */
function dpn_discord_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$channel_labels = dpn_discord_channel_labels();
	?>
	<div class="wrap">
		<h1>Discord通知設定</h1>
		<p>新規投稿の公開時、フォームの送信時、コメント投稿時に通知を送るDiscordのWebhook URLを設定します。<br />
		1行に1つずつ入力すると、複数のDiscordサーバー/チャンネルへ同時に通知できます。<br />
		用途別URLを空欄にすると、その用途はデフォルトURLへ通知されます(ログインは除く)。</p>
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
						<?php dpn_discord_render_test_button( '' ); ?>
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
								placeholder="<?php echo 'login' === $channel ? '未設定時は送りません(1行1URLで複数指定可能)' : '未設定時はデフォルトURLを使用(1行1URLで複数指定可能)'; ?>"
							><?php echo esc_textarea( get_option( $option_key, '' ) ); ?></textarea>
							<?php dpn_discord_render_test_button( $channel ); ?>
							<?php if ( 'login' === $channel ) : ?>
								<p class="description">ログイン画面からだれかが入ったときに、ユーザー・権限・IP・ブラウザを送ります。IPが載るので、関係者だけのチャンネルにしてください。この欄が空なら送りません(デフォルトURLや wp-config.php の DISCORD_WEBHOOK_URL にも送りません)。</p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button( '保存' ); ?>
		</form>
		<?php // 各欄の「この欄にテスト送信」が送るフォーム(設定のフォームの中には置けないので、外に置いて form 属性で結ぶ) ?>
		<form id="dpn-discord-test" method="post">
			<?php wp_nonce_field( 'dpn_discord_test_send' ); ?>
			<input type="hidden" name="dpn_discord_test" value="1" />
		</form>
		<p class="description">「この欄にテスト送信」は、保存してあるURLへ送ります。書き換えたときは、先に保存してください。</p>
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

	$labels  = dpn_discord_channel_labels();
	$channel = isset( $_POST['dpn_discord_channel'] ) ? sanitize_key( wp_unslash( $_POST['dpn_discord_channel'] ) ) : '';
	if ( ! isset( $labels[ $channel ] ) ) {
		return;
	}
	$urls = dpn_discord_option_to_urls( dpn_discord_channel_option_key( $channel ) );
	if ( empty( $urls ) ) {
		return;
	}

	$message = sprintf( '%s: 「%s」の欄のテスト通知です。', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $labels[ $channel ] );
	foreach ( $urls as $url ) {
		dpn_discord_post_to( $url, $message, 10 );
	}
	$notice = sprintf( '「%s」の欄の %d か所へテストメッセージを送信しました。Discordを確認してください。', $labels[ $channel ], count( $urls ) );
	add_action(
		'admin_notices',
		function () use ( $notice ) {
			echo '<div class="notice notice-success"><p>' . esc_html( $notice ) . '</p></div>';
		}
	);
}
add_action( 'admin_init', 'dpn_discord_handle_test_send' );

/**
 * Webhook URLの配列を取得する。
 * $channel を指定すると用途別URL('post'/'cf7'/'comment')を優先し、未設定ならデフォルトURLにフォールバックする。
 * wp-config.phpでDISCORD_WEBHOOK_URLが定義されている場合は、ログイン以外の全用途に対して常にそちらを最優先する(上級者向け)。
 * ログインは用途別URLだけを使う(未設定なら空)。
 */
function dpn_discord_get_webhook_urls( $channel = '' ) {
	$channel_keys = dpn_discord_channel_option_keys();
	if ( 'login' === $channel ) {
		return dpn_discord_option_to_urls( $channel_keys['login'] );
	}

	if ( defined( 'DISCORD_WEBHOOK_URL' ) && DISCORD_WEBHOOK_URL !== '' ) {
		return array( DISCORD_WEBHOOK_URL );
	}

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
function dpn_discord_send_message( $message, $channel = '', $timeout = 10 ) {
	$urls = dpn_discord_get_webhook_urls( $channel );
	if ( empty( $urls ) ) {
		return;
	}

	if ( function_exists( 'mb_strlen' ) && mb_strlen( $message ) > 1900 ) {
		$message = mb_substr( $message, 0, 1900 ) . "\n…(省略)";
	}

	foreach ( $urls as $url ) {
		dpn_discord_post_to( $url, $message, $timeout );
	}
}

/**
 * 1つのWebhookへ送る。
 */
function dpn_discord_post_to( $url, $message, $timeout ) {
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
			'timeout' => $timeout,
		)
	);
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

	// 公開の処理の途中ではアイキャッチやSEOの設定がまだ保存されておらず、
	// Discordがそのときのページを読むとカードが出ない(しかもURLごとに覚えられる)。
	// 少し待ってから WP-Cron で送る。
	if ( ! wp_next_scheduled( 'dpn_send_publish_notice', array( $post->ID ) ) ) {
		wp_schedule_single_event( time() + DPN_PUBLISH_NOTICE_DELAY, 'dpn_send_publish_notice', array( $post->ID ) );
	}
}
add_action( 'transition_post_status', 'dpn_notify_discord_on_publish', 10, 3 );

/**
 * 公開から DPN_PUBLISH_NOTICE_DELAY 秒後に、記事の公開をDiscordへ通知する。
 */
function dpn_send_publish_notice( $post_id ) {
	$post = get_post( $post_id );
	// 待っている間に非公開・下書きに戻された記事は送らない
	if ( ! $post || $post->post_status !== 'publish' ) {
		return;
	}

	$title = get_the_title( $post );
	$url   = get_permalink( $post );

	dpn_discord_send_message( sprintf( "新しい記事が公開されました！\n**%s**\n%s", $title, $url ), 'post' );
}
add_action( 'dpn_send_publish_notice', 'dpn_send_publish_notice' );

/**
 * フォームの送信内容をDiscordへ通知する。
 *
 * フォームのプラグインから、送信が済んだときに次のように呼ぶ。
 *   do_action( 'dpn_form_submitted', 'フォーム名', array( '項目名' => '値', ... ) );
 * Contact Form 7 から通知するつなぎ方は README を参照。
 */
function dpn_notify_discord_on_form_submit( $form_title, $fields ) {
	if ( empty( dpn_discord_get_webhook_urls( 'cf7' ) ) ) {
		return;
	}

	$lines = array();
	foreach ( (array) $fields as $label => $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ', ', $value );
		}
		$value = (string) $value;
		// 複数行の値(本文など)は項目名の次の行から書く
		$lines[] = strpos( $value, "\n" ) !== false
			? sprintf( "**%s**:\n%s", $label, $value )
			: sprintf( '**%s**: %s', $label, $value );
	}

	$message = sprintf(
		"フォームが送信されました。(%s)\n%s",
		$form_title,
		implode( "\n", $lines )
	);

	dpn_discord_send_message( $message, 'cf7' );
}
add_action( 'dpn_form_submitted', 'dpn_notify_discord_on_form_submit', 10, 2 );

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

/**
 * ログインしたときにDiscordへ通知する(ログイン画面から入ったとき。ログインしたままの再訪問では送らない)。
 * 2段階認証のプラグインが先に止めて、認証のあとで wp_login をもう一度呼ぶ場合は、そのときに送られる。
 * ログインを待たせないよう、Discordの応答は短く待つ。
 */
function dpn_notify_discord_on_login( $user_login, $user ) {
	if ( empty( dpn_discord_get_webhook_urls( 'login' ) ) || ! ( $user instanceof WP_User ) ) {
		return;
	}

	$names = wp_roles()->get_names();
	$roles = array();
	foreach ( (array) $user->roles as $role ) {
		$roles[] = isset( $names[ $role ] ) ? translate_user_role( $names[ $role ] ) : $role;
	}
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $ua ) > 200 ) {
		$ua = mb_substr( $ua, 0, 200 ) . '…';
	}

	$message = sprintf(
		"%s: ログインがありました。\nユーザー: %s(%s)\n権限: %s\nIP: %s\nブラウザ: %s\n日時: %s",
		wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		$user->display_name,
		$user_login,
		$roles ? implode( '・', $roles ) : 'なし',
		$ip !== '' ? $ip : '不明',
		$ua !== '' ? $ua : '不明',
		wp_date( 'Y-m-d H:i:s' )
	);

	dpn_discord_send_message( $message, 'login', 5 );
}
add_action( 'wp_login', 'dpn_notify_discord_on_login', 10, 2 );
