<?php

/**
 * @file plugins/importexport/copernicus/DOAJExportDom.inc.php
 *
 * Copyright (c) 2013-2017 Simon Fraser University
 * Copyright (c) 2003-2016 John Willinsky
 * Copyright (c) 2017 Andriy Semenets
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CopernicusExportDom
 * @ingroup plugins_importexport_copernicus
 *
 * @brief Copernicus import/export plugin DOM functions for export
 */

import('lib.pkp.classes.xml.XMLCustomWriter');

class CopernicusExportDom {
	/**
	 * Generate the export DOM tree for a given journal.
	 * @param $doc object DOM object
	 * @param $journal object Journal to export
	 * @param $selectedObjects array
	 */
	function generateJournalDom($doc, $journal, $selectedObjects) {
		$issueDao = DAORegistry::getDAO('IssueDAO');
		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$pubArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
		$articleDao = DAORegistry::getDAO('ArticleDAO');
		$journalId = $journal->getId();

		// Records node contains all articles, each called a record
		//$records = XMLCustomWriter::createElement($doc, 'records');
        $records = XMLCustomWriter::createElement($doc, 'ici-import');
		
		// retrieve selected issues
		$selectedIssues = array();
		if (isset($selectedObjects[COPERNICUS_EXPORT_ISSUES])) {
			$selectedIssues = $selectedObjects[COPERNICUS_EXPORT_ISSUES];
                    //write journal record into XML
                    $journalNodeData = XMLCustomWriter::createElement($doc, 'journal');
                    XMLCustomWriter::setAttribute($journalNodeData, 'issn', $journal->getSetting('printIssn'));
                    $journalNode = XMLCustomWriter::appendChild($records, $journalNodeData);                    
			// make sure the selected issues belong to the current journal
			foreach($selectedIssues as $key => $selectedIssueId) {
				$selectedIssue = $issueDao->getIssueById($selectedIssueId, $journalId);
				if (!$selectedIssue) {
                    unset($selectedIssues[$key]);
                } else {
                    //write issue record to XML
                    $journalIssueNodeData = XMLCustomWriter::createElement($doc, 'issue');
                    //get issue data
                    $issue = $issueDao->getIssueById($selectedIssueId);
                    /* --- set issue's data: publication date, volume, number, DOI --- */
                    XMLCustomWriter::setAttribute($journalIssueNodeData, 'number', $issue->getNumber());
                    XMLCustomWriter::setAttribute($journalIssueNodeData, 'volume', $issue->getVolume());
                    XMLCustomWriter::setAttribute($journalIssueNodeData, 'year', $issue->getYear());
                    XMLCustomWriter::setAttribute($journalIssueNodeData, 'publicationDate', CopernicusExportDom::formatDate($issue->getDatePublished()));
                    XMLCustomWriter::setAttribute($journalIssueNodeData, 'numberOfArticles', $issue->getNumArticles());
                    //assign issue record to a journal node
                    $journalIssueNode = XMLCustomWriter::appendChild($journalNode, $journalIssueNodeData);
                }
			}
		}

		// retrieve selected articles
		$selectedArticles = array();
		if (isset($selectedObjects[COPERNICUS_EXPORT_ARTICLES])) {
			$selectedArticles = $selectedObjects[COPERNICUS_EXPORT_ARTICLES];
		
			// make sure the selected articles belong to the current journal
			foreach($selectedArticles as $key => $selectedArticleId) {
				$selectedArticle = $articleDao->getArticle($selectedArticleId, $journalId);
				if (!$selectedArticle) unset($selectedArticles[$key]);
			}
		}

		$pubArticles = $pubArticleDao->getPublishedArticlesByJournalId($journalId);
		while ($pubArticle = $pubArticles->next()) {
			
			// check for selected issues:
			$issueId = $pubArticle->getIssueId();
			if (!empty($selectedIssues) && !in_array($issueId, $selectedIssues)) continue;

			$issue = $issueDao->getIssueById($issueId);
			if(!$issue) continue;
			
			// check for selected articles:
			$articleId = $pubArticle->getArticleId();
			if (!empty($selectedArticles) && !in_array($articleId, $selectedArticles)) continue;


			$section = $sectionDao->getSection($pubArticle->getSectionId());
			$articleNode = CopernicusExportDom::generateArticleDom($doc, $journal, $issue, $section, $pubArticle);

			//XMLCustomWriter::appendChild($records, $articleNode);
            //Copernicus's root node
            //TODO: get correct issue node if multiple issues exported
            XMLCustomWriter::appendChild($journalIssueNode, $articleNode);

			unset($issue, $section, $articleNode);
		}

		return $records;
	}

	/**
	 * Generate the DOM tree for a given article.
	 * @param $doc object DOM object
	 * @param $journal object Journal
	 * @param $issue object Issue
	 * @param $section object Section
	 * @param $article object Article
	 */
	function generateArticleDom($doc, $journal, $issue, $section, $article) {
		//article root
        $root = XMLCustomWriter::createElement($doc, 'article');

        /* --- Article Type --- */
		$typeNode = XMLCustomWriter::createChildWithText($doc, $root, 'type', CopernicusExportDom::mapArticleType(0), false);
        //XMLCustomWriter::setAttribute($root, 'type', 'articleType'); //TODO: change 
		
        /* --- Article meta-data by language --- */
        $langVersionNodeData = XMLCustomWriter::createElement($doc, 'languageVersion');
        $locale = $article->getLocale();
        //var_dump($article);
        //var_dump(substr($locale, 3, 2));
        //TODO: re-factor
        if (strlen($locale) == 5) {
            XMLCustomWriter::setAttribute($langVersionNodeData, 'language', substr($locale, 3, 2));
        } else {
            XMLCustomWriter::setAttribute($langVersionNodeData, 'language', CopernicusExportDom::mapLang(String::substr($locale, 0, 2)));
        }
        //article local language version root
        $langVersionNode = XMLCustomWriter::appendChild($root, $langVersionNodeData);
		
        $titleNode = XMLCustomWriter::createChildWithText($doc, $langVersionNode, 'title', $article->getTitle($locale));
        $abstractNode = XMLCustomWriter::createChildWithText($doc, $langVersionNode, 'abstract', $article->getAbstract($locale));

		/* --- FullText URL --- */
		$fullTextUrl = XMLCustomWriter::createChildWithText($doc, $langVersionNode, 'pdfFileUrl', Request::url(null, 'article', 'view', $article->getId()));
		XMLCustomWriter::setAttribute($fullTextUrl, 'format', 'html');
        
		/* --- Article's publication date, pages, DOI --- */
		if ($article->getDatePublished()) {
			XMLCustomWriter::createChildWithText($doc, $langVersionNode, 'publicationDate', CopernicusExportDom::formatDate($article->getDatePublished()), false);			
		}
		else {
			XMLCustomWriter::createChildWithText($doc, $langVersionNode, 'publicationDate', CopernicusExportDom::formatDate($issue->getDatePublished()), false);
		}

		/** --- FirstPage / LastPage (from PubMed plugin)---
		 * there is some ambiguity for online journals as to what
		 * "page numbers" are; for example, some journals (eg. JMIR)
		 * use the "e-location ID" as the "page numbers" in PubMed
		 */
		$pages = $article->getPages();
		if (preg_match("/([0-9]+)\s*-\s*([0-9]+)/i", $pages, $matches)) {
			// simple pagination (eg. "pp. 3-8")
			XMLCustomWriter::createChildWithText($doc, $langVersionNode, 'pageFrom', $matches[1]);
			XMLCustomWriter::createChildWithText($doc, $langVersionNode, 'pageTo', $matches[2]);
		} elseif (preg_match("/(e[0-9]+)/i", $pages, $matches)) {
			// elocation-id (eg. "e12")
			XMLCustomWriter::createChildWithText($doc, $langVersionNode, 'pageFrom', $matches[1]);
			XMLCustomWriter::createChildWithText($doc, $langVersionNode, 'pageTo', $matches[1]);
		}

		XMLCustomWriter::createChildWithText($doc, $langVersionNode, 'doi',  $article->getPubId('doi'), false);
		/* --- Article's publication date, pages, DOI --- */

		/* --- Keywords --- */
		$keywords = XMLCustomWriter::createElement($doc, 'keywords');
		XMLCustomWriter::appendChild($langVersionNode, $keywords);

		$subjects = array_map('trim', explode(';', $article->getSubject($article->getLocale())));

		foreach ($subjects as $keyword) {
			XMLCustomWriter::createChildWithText($doc, $keywords, 'keyword', $keyword, false);
		}

        
        //foreach ((array) $article->getTitle(null) as $locale => $title) {
		//	if (empty($title)) continue;

		//	$titleNode = XMLCustomWriter::createChildWithText($doc, $root, 'title', $title);
		//	if (strlen($locale) == 5) XMLCustomWriter::setAttribute($titleNode, 'language', CopernicusExportDom::mapLang(String::substr($locale, 0, 2)));
		//}
        
        
        //CopernicusExportDom::mapLang(String::substr($locale, 0, 2)
		/* --- Article Language --- */
		//XMLCustomWriter::createChildWithText($doc, $root, 'language', CopernicusExportDom::mapLang($article->getLanguage()), false);

		/* --- Publisher name (i.e. institution name) --- */
		//XMLCustomWriter::createChildWithText($doc, $root, 'publisher', $journal->getSetting('publisherInstitution'), false);

		/* --- Journal's title --- */
		//XMLCustomWriter::createChildWithText($doc, $root, 'journalTitle', $journal->getTitle($journal->getPrimaryLocale()), false);

		/* --- Identification Numbers --- */
		//XMLCustomWriter::createChildWithText($doc, $root, 'issn', $journal->getSetting('printIssn'), false);
		//XMLCustomWriter::createChildWithText($doc, $root, 'eissn', $journal->getSetting('onlineIssn'), false);



		//XMLCustomWriter::createChildWithText($doc, $root, 'volume',  $issue->getVolume(), false);

		//XMLCustomWriter::createChildWithText($doc, $root, 'issue',  $issue->getNumber(), false);




		//XMLCustomWriter::createChildWithText($doc, $root, 'publisherRecordId',  $article->getPublishedArticleId(), false);
//var_dump($article->getPublishedArticleId());
		//XMLCustomWriter::createChildWithText($doc, $root, 'documentType',  $article->getType($article->getLocale()), false);
//var_dump($article->getType($article->getLocale()));


		/* --- Authors and affiliations --- */
		$authors = XMLCustomWriter::createElement($doc, 'authors');
		XMLCustomWriter::appendChild($root, $authors);

		$affilList = CopernicusExportDom::generateAffiliationsList($article->getAuthors(), $article);

		foreach ($article->getAuthors() as $key=>$author) {
			$authorNode = CopernicusExportDom::generateAuthorDom($doc, $journal, $issue, $article, $key, $author, $affilList);
			XMLCustomWriter::appendChild($authors, $authorNode);
			unset($authorNode);
		}

		//if (!empty($affilList[0])) {
		//	$affils = XMLCustomWriter::createElement($doc, 'affiliationsList');
		//	XMLCustomWriter::appendChild($root, $affils);

		//	for ($i = 0; $i < count($affilList); $i++) {
		//		$affilNode = XMLCustomWriter::createChildWithText($doc, $affils, 'affiliationName', $affilList[$i]);
		//		XMLCustomWriter::setAttribute($affilNode, 'affiliationId', $i);
		//		unset($affilNode);
		//	}
		//}

		/* --- Abstract --- */
		//foreach ((array) $article->getAbstract(null) as $locale => $abstract) {
		//	if (empty($abstract)) continue;

		//	$abstractNode = XMLCustomWriter::createChildWithText($doc, $root, 'abstract', String::html2text($abstract));
		//	if (strlen($locale) == 5) XMLCustomWriter::setAttribute($abstractNode, 'language', CopernicusExportDom::mapLang(String::substr($locale, 0, 2)));
		//}





		return $root;
	}

	/**
	 * Generate the author export DOM tree.
	 * @param $doc object DOM object
	 * @param $journal object Journal
	 * @param $issue object Issue
	 * @param $article object Article
     * @param $key author's array key
	 * @param $author object Author
	 * @param $affilList array List of author affiliations
	 */
	function generateAuthorDom($doc, $journal, $issue, $article, $key, $author, $affilList) {
		$root = XMLCustomWriter::createElement($doc, 'author');

		XMLCustomWriter::createChildWithText($doc, $root, 'name', $author->getFirstName());
        XMLCustomWriter::createChildWithText($doc, $root, 'name2', $author->getMiddleName());
        XMLCustomWriter::createChildWithText($doc, $root, 'surname', $author->getLastName());
        //Polish affiliation        
        if ($author->getCountry()=='PL'||$author->getCountry()=='pl_PL'){
            XMLCustomWriter::createChildWithText($doc, $root, 'polishAffiliation', '1');
        } else {
            XMLCustomWriter::createChildWithText($doc, $root, 'polishAffiliation', '0');
        }
		XMLCustomWriter::createChildWithText($doc, $root, 'email', $author->getEmail(), false);
        
        XMLCustomWriter::createChildWithText($doc, $root, 'order', strval($key+1));
        
        XMLCustomWriter::createChildWithText($doc, $root, 'instituteAffiliation', $author->getAffiliation($article->getLocale()));
        
        XMLCustomWriter::createChildWithText($doc, $root, 'departmentAffiliation', $author->getBiography($article->getLocale()));
        
		//if(in_array($author->getAffiliation($article->getLocale()), $affilList)  && !empty($affilList[0])) {
		//	XMLCustomWriter::createChildWithText($doc, $root, 'instituteAffiliation', current(array_keys($affilList, $author->getAffiliation($article->getLocale()))));
		//}

		return $root;
	}

	/**
	 * Generate a list of affiliations among all authors of an article.
	 * @param $authors object Array of article authors
	 * @param $article Article
	 * @return array
	 */
	function generateAffiliationsList($authors, $article) {
		$affilList = array();

		foreach ($authors as $author) {
			if(!in_array($author->getAffiliation($article->getLocale()), $affilList)) {
				$affilList[] = $author->getAffiliation($article->getLocale()) ;
			}
		}

		return $affilList;
	}

	/* --- Utility functions: --- */

	/**
	 * Get the file extension of a filename.
	 * @param $filename
	 * @return string
	 */
	function file_ext($filename) {
		return strtolower_codesafe(str_replace('.', '', strrchr($filename, '.')));
	}

	/**
	 * Format a date by Y-m-d format.
	 * @param $date string
	 * @return string
	 */
	function formatDate($date) {
		if ($date == '') return null;
		return date('Y-m-d', strtotime($date));
	}

	/**
	 * Map a language from a 2-letter code to a 2-letter country code. 
	 * @param $val string 2-letter language code to map
	 * @return string
	 */
     //TODO: convert all!
	function mapLang($val) {
		switch ($val) {
			case "aa": return "aar"; break;
			case "ab": return "abk"; break;
			case "af": return "afr"; break;
			case "ak": return "aka"; break;
			case "sq": return "alb"; break;
			case "sqi": return "alb"; break;
			case "am": return "amh"; break;
			case "ar": return "ara"; break;
			case "an": return "arg"; break;
			case "hy": return "arm"; break;
			case "hye": return "arm"; break;
			case "as": return "asm"; break;
			case "av": return "ava"; break;
			case "ae": return "ave"; break;
			case "ay": return "aym"; break;
			case "az": return "aze"; break;
			case "ba": return "bak"; break;
			case "bm": return "bam"; break;
			case "eu": return "baq"; break;
			case "eus": return "baq"; break;
			case "be": return "bel"; break;
			case "bn": return "ben"; break;
			case "bh": return "bih"; break;
			case "bi": return "bis"; break;
			case "bo": return "tib"; break;
			case "bod": return "tib"; break;
			case "bs": return "bos"; break;
			case "br": return "bre"; break;
			case "bg": return "bul"; break;
			case "my": return "bur"; break;
			case "mya": return "bur"; break;
			case "ca": return "cat"; break;
			case "cs": return "cze"; break;
			case "ces": return "cze"; break;
			case "ch": return "cha"; break;
			case "ce": return "che"; break;
			case "zh": return "chi"; break;
			case "zho": return "chi"; break;
			case "cv": return "chv"; break;
			case "kw": return "cor"; break;
			case "co": return "cos"; break;
			case "cr": return "cre"; break;
			case "cy": return "wel"; break;
			case "cym": return "wel"; break;
			case "da": return "dan"; break;
			case "de": return "DE"; break;
			case "deu": return "DE"; break;
			case "dv": return "div"; break;
			case "nl": return "dut"; break;
			case "nld": return "dut"; break;
			case "dz": return "dzo"; break;
			case "el": return "gre"; break;
			case "ell": return "gre"; break;
			case "en": return "UK"; break;
			case "eo": return "epo"; break;
			case "et": return "est"; break;
			case "ee": return "ewe"; break;
			case "fo": return "fao"; break;
			case "fa": return "per"; break;
			case "fas": return "per"; break;
			case "fj": return "fij"; break;
			case "fi": return "fin"; break;
			case "fr": return "fre"; break;
			case "fra": return "fre"; break;
			case "fy": return "fry"; break;
			case "ff": return "ful"; break;
			case "ka": return "geo"; break;
			case "kat": return "geo"; break;
			case "gd": return "gla"; break;
			case "ga": return "gle"; break;
			case "gl": return "glg"; break;
			case "gv": return "glv"; break;
			case "gn": return "grn"; break;
			case "gu": return "guj"; break;
			case "ht": return "hat"; break;
			case "ha": return "hau"; break;
			case "he": return "heb"; break;
			case "hz": return "her"; break;
			case "hi": return "hin"; break;
			case "ho": return "hmo"; break;
			case "hr": return "scr"; break;
			case "hrv": return "scr"; break;
			case "hu": return "hun"; break;
			case "ig": return "ibo"; break;
			case "is": return "ice"; break;
			case "isl": return "ice"; break;
			case "io": return "ido"; break;
			case "ii": return "iii"; break;
			case "iu": return "iku"; break;
			case "ie": return "ile"; break;
			case "ia": return "ina"; break;
			case "id": return "ind"; break;
			case "ik": return "ipk"; break;
			case "it": return "ita"; break;
			case "jv": return "jav"; break;
			case "ja": return "jpn"; break;
			case "kl": return "kal"; break;
			case "kn": return "kan"; break;
			case "ks": return "kas"; break;
			case "kr": return "kau"; break;
			case "kk": return "kaz"; break;
			case "km": return "khm"; break;
			case "ki": return "kik"; break;
			case "rw": return "kin"; break;
			case "ky": return "kir"; break;
			case "kv": return "kom"; break;
			case "kg": return "kon"; break;
			case "ko": return "kor"; break;
			case "kj": return "kua"; break;
			case "ku": return "kur"; break;
			case "lo": return "lao"; break;
			case "la": return "lat"; break;
			case "lv": return "lav"; break;
			case "li": return "lim"; break;
			case "ln": return "lin"; break;
			case "lt": return "lit"; break;
			case "lb": return "ltz"; break;
			case "lu": return "lub"; break;
			case "lg": return "lug"; break;
			case "mk": return "mac"; break;
			case "mkd": return "mac"; break;
			case "mh": return "mah"; break;
			case "ml": return "mal"; break;
			case "mi": return "mao"; break;
			case "mri": return "mao"; break;
			case "mr": return "mar"; break;
			case "ms": return "may"; break;
			case "msa": return "may"; break;
			case "mg": return "mlg"; break;
			case "mt": return "mlt"; break;
			case "mo": return "mol"; break;
			case "mn": return "mon"; break;
			case "na": return "nau"; break;
			case "nv": return "nav"; break;
			case "nr": return "nbl"; break;
			case "nd": return "nde"; break;
			case "ng": return "ndo"; break;
			case "ne": return "nep"; break;
			case "nn": return "nno"; break;
			case "nb": return "nob"; break;
			case "no": return "nor"; break;
			case "ny": return "nya"; break;
			case "oc": return "oci"; break;
			case "oj": return "oji"; break;
			case "or": return "ori"; break;
			case "om": return "orm"; break;
			case "os": return "oss"; break;
			case "pa": return "pan"; break;
			case "pi": return "pli"; break;
			case "pl": return "PL"; break;
			case "pt": return "por"; break;
			case "ps": return "pus"; break;
			case "qu": return "que"; break;
			case "rm": return "roh"; break;
			case "ro": return "rum"; break;
			case "ron": return "rum"; break;
			case "rn": return "run"; break;
			case "ru": return "RU"; break;
			case "sg": return "sag"; break;
			case "sa": return "san"; break;
			case "sr": return "scc"; break;
			case "srp": return "scc"; break;
			case "si": return "sin"; break;
			case "sk": return "slo"; break;
			case "slk": return "slo"; break;
			case "sl": return "slv"; break;
			case "se": return "sme"; break;
			case "sm": return "smo"; break;
			case "sn": return "sna"; break;
			case "sd": return "snd"; break;
			case "so": return "som"; break;
			case "st": return "sot"; break;
			case "es": return "spa"; break;
			case "sc": return "srd"; break;
			case "ss": return "ssw"; break;
			case "su": return "sun"; break;
			case "sw": return "swa"; break;
			case "sv": return "swe"; break;
			case "ty": return "tah"; break;
			case "ta": return "tam"; break;
			case "tt": return "tat"; break;
			case "te": return "tel"; break;
			case "tg": return "tgk"; break;
			case "tl": return "tgl"; break;
			case "th": return "tha"; break;
			case "ti": return "tir"; break;
			case "to": return "ton"; break;
			case "tn": return "tsn"; break;
			case "ts": return "tso"; break;
			case "tk": return "tuk"; break;
			case "tr": return "tur"; break;
			case "tw": return "twi"; break;
			case "ug": return "uig"; break;
			case "uk": return "UA"; break;
			case "ur": return "urd"; break;
			case "uz": return "uzb"; break;
			case "ve": return "ven"; break;
			case "vi": return "vie"; break;
			case "vo": return "vol"; break;
			case "wa": return "wln"; break;
			case "wo": return "wol"; break;
			case "xh": return "xho"; break;
			case "yi": return "yid"; break;
			case "yo": return "yor"; break;
			case "za": return "zha"; break;
			case "zu": return "zul"; break;
			default: return "";
		}
	}
    
	/**
	 * Map a articleType
	 * @param $articleTypeId int articleTypeId 
	 * @return string
	 */
	function mapArticleType($articleTypeId = 0) {
        switch ($articleTypeId) {
            case 0: return "ORIGINAL_ARTICLE"; break;
            case 1: return "REVIEW_ARTICLE"; break;
            case 2: return "SHORT_COMMUNICATION"; break;
            case 3: return "COMMENTARY_ON_THE_LAW"; break;
            case 4: return "SCIENTIFIC_REVIEW"; break;
            case 5: return "GUIDELINES"; break;
            case 6: return "POPULAR_SCIENCE_ARTICLE"; break;
            case 7: return "OTHERS_NONCITABLE"; break;
            case 8: return "OTHERS_CITABLE"; break;
            case 9: return "CASE_STUDY"; break;
            default: return "ORIGINAL_ARTICLE";
        }
    }
}

?>
