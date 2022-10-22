<?php
/**
 * As configurações básicas do WordPress
 *
 * O script de criação wp-config.php usa esse arquivo durante a instalação.
 * Você não precisa usar o site, você pode copiar este arquivo
 * para "wp-config.php" e preencher os valores.
 *
 * Este arquivo contém as seguintes configurações:
 *
 * * Configurações do banco de dados
 * * Chaves secretas
 * * Prefixo do banco de dados
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Configurações do banco de dados - Você pode pegar estas informações com o serviço de hospedagem ** //
/** O nome do banco de dados do WordPress */
define( 'DB_NAME', 'tratagua' );

/** Usuário do banco de dados MySQL */
define( 'DB_USER', 'filipeps' );

/** Senha do banco de dados MySQL */
define( 'DB_PASSWORD', 'opifilipe' );

/** Nome do host do MySQL */
define( 'DB_HOST', 'localhost' );

/** Charset do banco de dados a ser usado na criação das tabelas. */
define( 'DB_CHARSET', 'utf8mb4' );

/** O tipo de Collate do banco de dados. Não altere isso se tiver dúvidas. */
define( 'DB_COLLATE', '' );

/**#@+
 * Chaves únicas de autenticação e salts.
 *
 * Altere cada chave para um frase única!
 * Você pode gerá-las
 * usando o {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org
 * secret-key service}
 * Você pode alterá-las a qualquer momento para invalidar quaisquer
 * cookies existentes. Isto irá forçar todos os
 * usuários a fazerem login novamente.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'tt*8};2v?K%hbiUZRg;4Ax4s^+]aBY8m/}}.;w^82^tuIJV0&&D5e)/dB6kCkV+$' );
define( 'SECURE_AUTH_KEY',  '5wLr1qNxU+%+>BaK9QQrExOBb#_>M e^Qyum:Di2/M:>Vd06O>ys:yrEog9 T*I>' );
define( 'LOGGED_IN_KEY',    'OsG9<?HNM`+iu23wt:=S4GYf}%; A#x8LH3MHX=g7sy{,+{CkzC`=bAfthU75Cv:' );
define( 'NONCE_KEY',        'sl7:/!@rTTfAqFoY2|};?Vo%F.k,|(!ydE(ik-cmr2t<5R>0!?0[RIk|B!Y6nQ= ' );
define( 'AUTH_SALT',        ':M]5hXD}8D|IjZs5XE!`]tCtkGqMP}70e*#(t#s1Vyrk_4&8)AV*%1If%y]C>mOM' );
define( 'SECURE_AUTH_SALT', 'H~=%!^A)~z;>)$5;Hlju?u6S@[#f@dB^6p#*rb~INHS:G-!Lx&{_Y~2w=_drKoCk' );
define( 'LOGGED_IN_SALT',   'ZBuZF}N{agfvEab6@Qe6$gIOB ;U,R`;rO&1Pub2dEW]mBZKsCR^@w<Q#s^E=S{ ' );
define( 'NONCE_SALT',       '1cE$U@~g^ln@_f~Xg3jpuzti}u7Y8==7w:(S.AO,NC.l=mD?5(JtywtX/mW?VJX|' );

/**#@-*/

/**
 * Prefixo da tabela do banco de dados do WordPress.
 *
 * Você pode ter várias instalações em um único banco de dados se você der
 * um prefixo único para cada um. Somente números, letras e sublinhados!
 */
$table_prefix = 'wp_';

/**
 * Para desenvolvedores: Modo de debug do WordPress.
 *
 * Altere isto para true para ativar a exibição de avisos
 * durante o desenvolvimento. É altamente recomendável que os
 * desenvolvedores de plugins e temas usem o WP_DEBUG
 * em seus ambientes de desenvolvimento.
 *
 * Para informações sobre outras constantes que podem ser utilizadas
 * para depuração, visite o Codex.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Adicione valores personalizados entre esta linha até "Isto é tudo". */



/* Isto é tudo, pode parar de editar! :) */

/** Caminho absoluto para o diretório WordPress. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Configura as variáveis e arquivos do WordPress. */
require_once ABSPATH . 'wp-settings.php';
