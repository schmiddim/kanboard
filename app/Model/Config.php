<?php

namespace Model;

use SimpleValidator\Validator;
use SimpleValidator\Validators;
use Core\Translator;
use Core\Security;
use Core\Session;

/**
 * Config model
 *
 * @package  model
 * @author   Frederic Guillot
 */
class Config extends Base
{
    /**
     * SQL table name
     *
     * @var string
     */
    const TABLE = 'settings';

    /**
     * Get available timezones
     *
     * @access public
     * @return array
     */
    public function getTimezones()
    {
        $timezones = timezone_identifiers_list();
        return array_combine(array_values($timezones), $timezones);
    }

    /**
     * Get available languages
     *
     * @access public
     * @return array
     */
    public function getLanguages()
    {
        // Sorted by value
        return array(
            'de_DE' => 'Deutsch',
            'en_US' => 'English',
            'es_ES' => 'Español',
            'fr_FR' => 'Français',
            'it_IT' => 'Italiano',
            'pl_PL' => 'Polski',
            'pt_BR' => 'Português (Brasil)',
            'ru_RU' => 'Русский',
            'fi_FI' => 'Suomi',
            'sv_SE' => 'Svenska',
            'zh_CN' => '中文(简体)',
            'ja_JP' => '日本語',
        );
    }

    /**
     * Get a config variable from the session or the database
     *
     * @access public
     * @param  string   $name            Parameter name
     * @param  string   $default_value   Default value of the parameter
     * @return string
     */
    public function get($name, $default_value = '')
    {
        if (! Session::isOpen()) {
            $value = $this->db->table(self::TABLE)->eq('option', $name)->findOneColumn('value');
            return $value ?: $default_value;
        }

        if (! isset($_SESSION['config'][$name])) {
            $_SESSION['config'] = $this->getAll();
        }

        if (! empty($_SESSION['config'][$name])) {
            return $_SESSION['config'][$name];
        }

        return $default_value;
    }

    /**
     * Get all settings
     *
     * @access public
     * @return array
     */
    public function getAll()
    {
        return $this->db->table(self::TABLE)->listing('option', 'value');
    }

    /**
     * Save settings in the database
     *
     * @access public
     * @param  $values  array   Settings values
     * @return boolean
     */
    public function save(array $values)
    {
        foreach ($values as $option => $value) {

            $result = $this->db->table(self::TABLE)->eq('option', $option)->update(array('value' => $value));

            if (! $result) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reload settings in the session and the translations
     *
     * @access public
     */
    public function reload()
    {
        $_SESSION['config'] = $this->getAll();
        $this->setupTranslations();
    }

    /**
     * Load translations
     *
     * @access public
     */
    public function setupTranslations()
    {
        $language = $this->get('application_language', 'en_US');

        if ($language !== 'en_US') {
            Translator::load($language);
        }
    }

    /**
     * Set timezone
     *
     * @access public
     */
    public function setupTimezone()
    {
        date_default_timezone_set($this->get('application_timezone', 'UTC'));
    }

    /**
     * Optimize the Sqlite database
     *
     * @access public
     * @return boolean
     */
    public function optimizeDatabase()
    {
        return $this->db->getconnection()->exec("VACUUM");
    }

    /**
     * Compress the Sqlite database
     *
     * @access public
     * @return string
     */
    public function downloadDatabase()
    {
        return gzencode(file_get_contents(DB_FILENAME));
    }

    /**
     * Get the Sqlite database size in bytes
     *
     * @access public
     * @return integer
     */
    public function getDatabaseSize()
    {
        return DB_DRIVER === 'sqlite' ? filesize(DB_FILENAME) : 0;
    }

    /**
     * Regenerate a token
     *
     * @access public
     * @param  string   $option   Parameter name
     */
    public function regenerateToken($option)
    {
        return $this->db->table(self::TABLE)
                 ->eq('option', $option)
                 ->update(array('value' => Security::generateToken()));
    }
}
