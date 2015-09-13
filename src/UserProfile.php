<?php

namespace HybridAuth\SkautIS;

/**
 * Vylepšený Hybrid_User_Profile obsahující navíc i nějaká data specifická pro SkautIS
 */
class UserProfile extends \Hybrid_User_Profile {

	/**
	 * Hlavní jednotka, kde je uživatel registrován
	 *
	 * @var Unit
	 */
	public $unit = null;

	/**
	 * Pole rolí, které uživatel má
	 *
	 * @var array of Role
	 */
	public $roles = array();

	/**
	 * Identifikátor osoby
	 *
	 * @var int
	 */
	public $personId = null;

	/**
	 * Je registrovaným členem Junáka?
	 *
	 * @var bool
	 */
	public $isMember = null;

	/**
	 * Fotka uživatele (jako binární data)
	 *
	 * @var binary|null
	 */
	public $photoData = null;

	/**
	 * Typ dat, která jsou v $photoData
	 *
	 * @var string jpg, png
	 */
	public $photoType = null;


}
