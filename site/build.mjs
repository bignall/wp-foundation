/**
 * Builds the GitHub Pages site into _site/.
 *
 * The landing page comes from site/index.html; every guide in docs/ is rendered from Markdown
 * into the same layout. Run with `npm run site:build`, then open _site/index.html.
 */
import { cpSync, existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join, posix } from 'node:path';
import { fileURLToPath } from 'node:url';
import { Marked } from 'marked';
import hljs from 'highlight.js/lib/core';
import bash from 'highlight.js/lib/languages/bash';
import json from 'highlight.js/lib/languages/json';
import php from 'highlight.js/lib/languages/php';

hljs.registerLanguage( 'bash', bash );
hljs.registerLanguage( 'json', json );
hljs.registerLanguage( 'php', php );

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const out = join( root, '_site' );
const repo = 'https://github.com/bignall/wp-foundation';
const packageName = 'bignall/wp-foundation';

// Guides, in reading order, with the summary shown on the home page and in search results.
const summaries = {
	'getting-started': 'Require the library, describe your plugin in its main file, and start your code once requirements pass.',
	lifecycle: 'What runs on activation, on every request, on upgrade, on deactivation and on uninstall.',
	requirements: 'Check PHP and WordPress versions, extensions and other plugins, and add your own checks.',
	tasks: 'Options, roles, cron and rewrite rules out of the box, and how to write your own tasks.',
	database: 'Custom tables kept in sync with dbDelta, and versioned migrations that resume after a failure.',
	'deactivation-survey': 'Ask why people deactivate, customise the reasons, and send answers where you want.',
	multisite: 'Network activation, new sites, deleted sites, and how to handle very large networks.',
	settings: 'Keep settings in one option: defaults, per-request cache, sanitizing, registration and field names.',
	notices: 'Show a notice on the next admin page after a form redirects, without writing it yourself.',
	prefixing: 'Bundle the library safely alongside other plugins by prefixing it with Strauss.',
	releasing: 'Build a small release zip that leaves out Composer\'s dev tools, and check the prefixing.',
	hooks: 'Every action, filter and option the framework adds, with their arguments.',
};
const docs = Object.keys( summaries );

const read = ( path ) => readFileSync( join( root, path ), 'utf8' );

const escapeHtml = ( s ) =>
	String( s ).replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' } )[ c ] );

const slugify = ( text ) =>
	text
		.toLowerCase()
		.replace( /<[^>]+>/g, '' )
		.replace( /&[a-z]+;/g, '' )
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-|-$/g, '' );

/**
 * Syntax-highlight a code sample. Snippets in the docs often omit `<?php`, so PHP is
 * highlighted as embedded code.
 */
function highlight( code, lang ) {
	if ( lang === 'php' ) {
		const hasOpenTag = code.trimStart().startsWith( '<?php' );
		const html = hljs.highlight( hasOpenTag ? code : `<?php\n${ code }`, { language: 'php' } ).value;
		return hasOpenTag ? html : html.replace( /^<span class="hljs-meta">&lt;\?php<\/span>\n/, '' );
	}
	if ( lang && hljs.getLanguage( lang ) ) {
		return hljs.highlight( code, { language: lang } ).value;
	}
	return escapeHtml( code );
}

/**
 * Rewrite a link found in a Markdown file so it works on the site.
 *
 * @param {string} href     Link target as written.
 * @param {string} fromPath Repository path of the file containing the link.
 */
function rewriteLink( href, fromPath ) {
	if ( /^([a-z]+:|#|\/)/i.test( href ) ) {
		return href;
	}
	const [ path, hash = '' ] = href.split( '#' );
	const resolved = posix.normalize( posix.join( posix.dirname( fromPath ), path ) );
	const anchor = hash ? `#${ hash }` : '';

	const doc = resolved.match( /^docs\/([a-z0-9-]+)\.md$/ );
	if ( doc && docs.includes( doc[ 1 ] ) ) {
		return `${ doc[ 1 ] }.html${ anchor }`;
	}

	// Anything else lives in the repository.
	const isFile = /\.[a-z0-9]+$/i.test( resolved );
	return `${ repo }/${ isFile ? 'blob' : 'tree' }/main/${ resolved }${ anchor }`;
}

function markdown( source, fromPath ) {
	const headings = [];
	const marked = new Marked( {
		gfm: true,
		renderer: {
			heading( { tokens, depth } ) {
				const text = this.parser.parseInline( tokens );
				const id = slugify( text );
				if ( depth === 2 ) {
					headings.push( { id, text } );
				}
				if ( depth === 1 ) {
					return `<h1>${ text }</h1>\n`;
				}
				return `<h${ depth } id="${ id }"><a class="anchor" href="#${ id }" aria-hidden="true" tabindex="-1">#</a>${ text }</h${ depth }>\n`;
			},
			code( { text, lang } ) {
				const language = ( lang || '' ).split( /\s/ )[ 0 ];
				return `<pre class="code" data-lang="${ escapeHtml( language ) }"><code>${ highlight( text, language ) }</code></pre>\n`;
			},
			link( { href, title, tokens } ) {
				const target = rewriteLink( href, fromPath );
				// A link whose text is a guide's file name shows the guide's title instead.
				const linked = docMeta.find( ( d ) => `${ d.slug }.md` === href.split( '#' )[ 0 ] );
				if ( linked && tokens.length === 1 && tokens[ 0 ].text === `${ linked.slug }.md` ) {
					return `<a href="${ escapeHtml( target ) }">${ escapeHtml( linked.title ) }</a>`;
				}
				const external = /^https?:/.test( target ) ? ' rel="noopener"' : '';
				const titleAttr = title ? ` title="${ escapeHtml( title ) }"` : '';
				return `<a href="${ escapeHtml( target ) }"${ titleAttr }${ external }>${ this.parser.parseInline( tokens ) }</a>`;
			},
		},
	} );

	// Let wide tables scroll on narrow screens.
	const html = marked.parse( source ).replace( /<table>/g, '<div class="table-wrap"><table>' ).replace( /<\/table>/g, '</table></div>' );

	return { html, headings };
}

function titleOf( source ) {
	const match = source.match( /^#\s+(.+)$/m );
	return match ? match[ 1 ].trim() : 'Untitled';
}

function versionFromChangelog() {
	const match = read( 'CHANGELOG.md' ).match( /^## \[(\d+\.\d+\.\d+)\]/m );
	return match ? match[ 1 ] : 'dev';
}

const version = versionFromChangelog();
const docMeta = docs.map( ( slug ) => {
	const source = read( `docs/${ slug }.md` );
	return { slug, source, title: titleOf( source ) };
} );

function layout( { title, description, body, active, bodyClass = '', head = '', scripts = '' } ) {
	const pageTitle = active === 'home' ? 'WP Foundation: lifecycle framework for WordPress plugins' : `${ title } · WP Foundation`;
	return `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${ escapeHtml( pageTitle ) }</title>
<meta name="description" content="${ escapeHtml( description ) }">
<meta property="og:title" content="${ escapeHtml( pageTitle ) }">
<meta property="og:description" content="${ escapeHtml( description ) }">
<meta name="theme-color" content="#1f2a44">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/site.css">
${ head }</head>
<body class="${ bodyClass }">
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
	<div class="wrap header-inner">
		<a class="brand" href="index.html" aria-label="WP Foundation home">
			<img src="assets/favicon.svg" alt="" width="28" height="28">
			<span>WP Foundation</span>
		</a>
		<nav class="site-nav" aria-label="Main">
			<a href="getting-started.html"${ active === 'docs' ? ' aria-current="page"' : '' }>Docs</a>
			<a href="${ repo }/releases" rel="noopener">v${ version }</a>
			<a href="${ repo }" rel="noopener" class="nav-github">GitHub</a>
		</nav>
	</div>
</header>
<main id="main">
${ body }
</main>
<footer class="site-footer">
	<div class="wrap footer-inner">
		<p>WP Foundation is free software, licensed <a href="${ repo }/blob/main/LICENSE" rel="noopener">GPL-2.0-or-later</a>, the same license as WordPress.</p>
		<p><a href="${ repo }" rel="noopener">Source</a> · <a href="${ repo }/issues" rel="noopener">Issues</a> · <a href="${ repo }/blob/main/CHANGELOG.md" rel="noopener">Changelog</a> · <a href="https://packagist.org/packages/${ packageName }" rel="noopener">Packagist</a></p>
	</div>
</footer>
<script src="assets/site.js" defer></script>
${ scripts }</body>
</html>
`;
}

function docPage( doc, index ) {
	const { html, headings } = markdown( doc.source, `docs/${ doc.slug }.md` );
	const prev = docMeta[ index - 1 ];
	const next = docMeta[ index + 1 ];

	const sidebar = docMeta
		.map( ( d ) => `<li><a href="${ d.slug }.html"${ d.slug === doc.slug ? ' aria-current="page"' : '' }>${ escapeHtml( d.title ) }</a></li>` )
		.join( '\n' );

	const toc = headings.length > 1
		? `<nav class="toc" aria-label="On this page"><p class="toc-title">On this page</p><ul>${ headings
			.map( ( h ) => `<li><a href="#${ h.id }">${ h.text }</a></li>` )
			.join( '' ) }</ul></nav>`
		: '';

	const pager = `<nav class="pager" aria-label="Guides">
		${ prev ? `<a class="pager-prev" href="${ prev.slug }.html"><span>Previous</span>${ escapeHtml( prev.title ) }</a>` : '<span></span>' }
		${ next ? `<a class="pager-next" href="${ next.slug }.html"><span>Next</span>${ escapeHtml( next.title ) }</a>` : '<span></span>' }
	</nav>`;

	const body = `<div class="wrap docs-layout">
	<aside class="docs-sidebar">
		<details class="docs-menu" open>
			<summary>Guides</summary>
			<ul>${ sidebar }</ul>
		</details>
	</aside>
	<article class="prose">
		${ html }
		<p class="edit-link"><a href="${ repo }/edit/main/docs/${ doc.slug }.md" rel="noopener">Edit this page on GitHub</a></p>
		${ pager }
	</article>
	${ toc }
</div>`;

	return layout( { title: doc.title, description: summaries[ doc.slug ], body, active: 'docs', bodyClass: 'is-docs' } );
}

function surveyDemoScript() {
	// Mirrors DeactivationSurvey::defaultReasons() and scriptConfig() with a remote handler.
	const config = {
		basename: 'acme-bookings/acme-bookings.php',
		ajaxUrl: 'demo:deactivation-feedback',
		action: 'acme_bookings_deactivation_feedback',
		nonce: 'demo',
		maxLength: 1000,
		askConsent: true,
		reasons: [
			{ id: 'temporary', label: "It's a temporary deactivation, I'm troubleshooting", detailsPrompt: null },
			{ id: 'found_better', label: 'I found a better plugin', detailsPrompt: 'Which plugin?' },
			{ id: 'no_longer_needed', label: 'I no longer need the plugin', detailsPrompt: null },
			{ id: 'not_working', label: "It isn't working or broke my site", detailsPrompt: 'What went wrong?' },
			{ id: 'missing_feature', label: "It's missing a feature I need", detailsPrompt: 'Which feature?' },
			{ id: 'too_complicated', label: "It's too hard to set up or use", detailsPrompt: 'What was confusing?' },
			{ id: 'other', label: 'Other', detailsPrompt: 'Please tell us more' },
		],
		strings: {
			title: 'Quick question before you deactivate',
			intro: 'If you have a moment, please tell us why. It helps us improve.',
			privacy: 'Your answer, plugin and WordPress versions are sent to the plugin developer. No personal data is included.',
			consent: 'Include my site address so the developer can follow up',
			submit: 'Submit & deactivate',
			skip: 'Skip & deactivate',
			cancel: 'Cancel',
			required: 'Please choose a reason, or skip.',
		},
	};
	return `${ read( 'src/Feedback/assets/deactivation-survey.js' ).trim() }(${ JSON.stringify( config ) });\n`;
}

function landingPage() {
	let html = read( 'site/index.html' );

	const docCards = docMeta
		.map( ( d ) => `<li><a href="${ d.slug }.html"><strong>${ escapeHtml( d.title ) }</strong><span>${ escapeHtml( summaries[ d.slug ] ) }</span></a></li>` )
		.join( '\n' );

	// Replace {{code:lang}}…{{/code}} blocks with highlighted code.
	html = html.replace( /\{\{code:(\w+)\}\}\n?([\s\S]*?)\{\{\/code\}\}/g, ( _, lang, code ) =>
		`<pre class="code" data-lang="${ lang }"><code>${ highlight( code.replace( /\n$/, '' ), lang ) }</code></pre>`
	);
	html = html
		.replaceAll( '{{version}}', version )
		.replaceAll( '{{repo}}', repo )
		.replaceAll( '{{package}}', packageName )
		.replaceAll( '{{docCards}}', docCards );

	return layout( {
		title: 'WP Foundation',
		description: 'A Composer library that handles requirement checks, activation, upgrades and migrations, uninstall cleanup, multisite and a deactivation survey for WordPress plugins.',
		body: html,
		active: 'home',
		bodyClass: 'is-home',
		head: '<link rel="stylesheet" href="assets/survey.css">\n',
		scripts: '<script src="assets/survey-demo.js" defer></script>\n',
	} );
}

// Build.
rmSync( out, { recursive: true, force: true } );
mkdirSync( join( out, 'assets' ), { recursive: true } );

cpSync( join( root, 'site/assets' ), join( out, 'assets' ), { recursive: true } );
writeFileSync( join( out, 'assets/survey.css' ), read( 'src/Feedback/assets/deactivation-survey.css' ) );
writeFileSync( join( out, 'assets/survey-demo.js' ), surveyDemoScript() );
writeFileSync( join( out, '.nojekyll' ), '' );

writeFileSync( join( out, 'index.html' ), landingPage() );
docMeta.forEach( ( doc, i ) => writeFileSync( join( out, `${ doc.slug }.html` ), docPage( doc, i ) ) );

if ( ! existsSync( join( out, 'index.html' ) ) ) {
	throw new Error( 'Build failed: no index.html' );
}
console.log( `Built WP Foundation site v${ version }: ${ docMeta.length + 1 } pages in _site/` );
