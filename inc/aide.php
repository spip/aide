<?php

/***************************************************************************\
 *  SPIP, Système de publication pour l'internet                           *
 *                                                                         *
 *  Copyright © avec tendresse depuis 2001                                 *
 *  Arnaud Martin, Antoine Pitrou, Philippe Rivière, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribué sous licence GNU/GPL.     *
 *  Pour plus de détails voir le fichier COPYING.txt ou l'aide en ligne.   *
\***************************************************************************/

/**
 * Gestion de l'aide en ligne de SPIP
 *
 * L'aide en ligne de SPIP est disponible sous forme d'articles de www.spip.net
 * qui ont des repères nommés arttitre, artdesc etc.
 *
 * La fonction `inc_aider_dist` reçoit soit ces repères,
 * soit le nom du champ de saisie, le nom du squelette le contenant et enfin
 * l'environnement d'exécution du squelette (inutilisé pour le moment).
 *
 * Le tableau global `aider_index` donne ces repères.
 *
 * @package SPIP\Core\Aider
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

include_spip('inc/filtres');


/**
 * Déclaration de l'index d'aide
 */
function aide_index(): array {
	static $index = null;
	if ($index === null) {
		$index = [];
		$index['raccourcis'] = [
			'resume', 'simple', 'lien', 'note', 'citation',
			'tableau', 'liste', 'glossaire', 'ancre', 'code'
		];
		$index = pipeline('aide_index', $index);
	}
	return $index;
}

/**
 * Quelques mappages d'alias historiques <= SPIP 4
 */
function aide_index_alias_historiques(): array {
	return [
		'text_area' => 'raccourcis',
		'chapo' => 'raccourcis',
	];
}


/**
 * Générer un lien d'aide (icône + lien)
 *
 * @uses aider_icone()
 *
 * @param string $aide
 *    clé d'identification du groupe d'aide souhaité. Peut indiquer une entrée spécifique.
 *    Tel que 'raccourcis' ou 'raccourcis/liens'
 * @return string
 *    icone et lien…
 *    vide si on ne trouve pas le groupe d'aide demandé.
 **/
function inc_aide_dist($aide = ''): string {
	$index = aide_index();
	if (!$aide) {
		// pour le moment rien sur entrée vide...
		return '';
	}

	$alias = aide_index_alias_historiques();
	if (isset($alias[$aide])) {
		$aide = $alias[$aide];
	}

	$aide = explode('/', $aide, 2);
	$groupe = array_shift($aide);
	$entree = $aide ? reset($aide) : '';
	if (!isset($index[$groupe])) {
		return '';
	}

	$url = aide_generer_url($groupe, $entree);

	return aider_icone($url);
}

/** Génère l'url d'une entrée d'aide */
function aide_generer_url(string $groupe, ?string $entree = null): string {
	$url = generer_url_ecrire('aide');
	$url = parametre_url($url, 'aide', $groupe, '&');
	if ($entree) {
		$url = parametre_url($url, 'entree', $entree, '&');
	}
	return $url;
}

/**
 * Créer l'icône d'aide
 *
 * @global string $spip_lang
 * @global string $spip_lang_rtl
 *
 * @param string $url
 *         URL vers l'aide
 * @param string $clic
 *         Contenu de la balise de lien.
 * @return string
 *         Icone d'aide
 */
function aider_icone($url, $clic = '') {

	include_spip('inc/lang');
	if (!$clic) {
		$t = _T('titre_image_aide');
		$clic = http_img_pack(
			'aide' . aide_lang_dir($GLOBALS['spip_lang'], $GLOBALS['spip_lang_rtl']) . '-16.png',
			_T('info_image_aide'),
			" title=\"$t\" class='aide'"
		);
	}

	return "\n&nbsp;&nbsp;<a class='aide popin'\nhref='"
	. $url
	. "' target='_blank'>"
	. $clic
	. '</a>';
}



// Affichage du menu de gauche avec analyse de la section demandee
function aide_data(?string $groupe = null): array {
	static $menu = [];
	if (!isset($menu[$groupe])) {
		include_spip('inc/aide');
		$index = aide_index();
		if ($groupe === null) {
			// juste un menu…
			$menu[$groupe] = [
				'titre' => _T('icone_aide_ligne'),
				'entrees' => [],
			];
			foreach (array_keys($index) as $group) {
				$menu[$groupe]['entrees'][] = [
					'titre' => _T('aide:' . $group),
					'link' => aide_generer_url($group),
					'texte' => aide_contenu($group, '_intro'),
				];
			}
		} else {
			$entrees = $index[$groupe] ?? null;
			if ($entrees === null) {
				// le groupe est introuvable…
				return $menu[$groupe] = [];
			}
			$menu[$groupe] = [
				'titre' => _T('aide:' . $groupe),
				'intro' => aide_contenu($groupe, '_intro'),
				'entrees' => [],
			];
			foreach ($entrees as $entree) {
				$menu[$groupe]['entrees'][] = [
					'titre' => _T('aide:' . $groupe . '_' . $entree),
					'groupe' => $menu[$groupe]['titre'],
					'texte' => aide_contenu($groupe, $entree),
				];
			}
		}
	}
	return $menu[$groupe];
}


/**
 * Retrouve le contenu d'une aide demandée
 */
function aide_contenu(string $groupe, string $entree): string {
	$langues = aide_langues();
	foreach ($langues as $lang) {
		$aide_entree = find_in_path("aide/$lang/$groupe/$entree.spip");
		if ($aide_entree) {
			lire_fichier($aide_entree, $content);
			return $content;
		}
	}
	return '';
}

/**
 * Retourne la liste des langues dans laquelle une aide est cherchée
 * On part par défaut de spip_lang
 *
 * pt_br => pt => langue_du_site => langue_par_defaut => fr
 */
function aide_langues($lang = null): array {
	if ($lang === null) {
		$lang = $GLOBALS['spip_lang'];
	}
	static $langues = [];
	if (!isset($langues[$lang])) {
		$_langues = [$lang];
		if (strpos($lang, '_') !== false) {
			$l = explode('_', $lang);
			while (count($l) > 1) {
				array_pop($l);
				$_langues[] = implode('_', $l);
			}
		}

		$_langues[] = $GLOBALS['meta']['langue_site'];
		$_langues[] = _LANGUE_PAR_DEFAUT;
		$_langues[] = 'fr';
		$_langues = array_unique($_langues);
		$langues[$lang] = $_langues;
	}
	return $langues[$lang];
}
