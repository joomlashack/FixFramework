<?php
/**
 * @package   FixFramework
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2020 Joomlashack.com. All rights reserved
 * @license   https://www.gnu.org/licenses/gpl.html GNU/GPL
 *
 * This file is part of FixFramework.
 *
 * FixFramework is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * FixFramework is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with FixFramework.  If not, see <https://www.gnu.org/licenses/>.
 */

use Joomla\Archive\Archive;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Installer\Adapter\PluginAdapter;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

defined('_JEXEC') or die();

/**
 * Custom installer script
 */
class PlgsystemfixframeworkInstallerScript
{
    /**
     * @var Installer
     */
    protected $installer = null;

    /**
     * @var CMSApplication
     */
    protected $app = null;

    /**
     * PlgsystemfixframeworkInstallerScript constructor.
     *
     * @param PluginAdapter $parent
     *
     * @return void
     * @throws Exception
     */
    public function __construct(PluginAdapter $parent)
    {
        $this->installer = $parent->getParent();
        $this->app       = Factory::getApplication();
    }

    /**
     * @return bool
     */
    public function preflight(): bool
    {
        $this->sendMessage(
            '<h3>Please Note</h3>'
            . '<p>This plugin is designed to fail on installation. '
            . 'It will attempt to correct problems with the Joomlashack Framework.</p>'
        );

        if (
            $this->clearDatabase()
            && $this->clearFolder()
        ) {
            $this->reinstall();

        } else {
            $this->sendMessage('Unable to attempt reinstalling Framework');
        }

        return false;
    }

    /**
     * @param string $text
     *
     * @return void
     */
    protected function sendMessage($text)
    {
        $this->app->enqueueMessage($text, 'notice');
    }

    /**
     * @return bool
     */
    protected function clearDatabase(): bool
    {
        $success = true;

        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('extension_id')
            ->from('#__extensions')
            ->where([
                $db->quoteName('type') . '=' . $db->quote('library'),
                $db->quoteName('element') . '=' . $db->quote('allediaframework')
            ]);

        $id = (int)$db->setQuery($query)->loadResult();

        if ($id) {
            $message = 'Attempting standard uninstall ';

            try {
                /** @var InstallerModelManage $model */
                $model = BaseDatabaseModel::getInstance('Manage', 'InstallerModel');
                if ($model->remove([$id])) {
                    $message .= '[OK]';

                } else {
                    $this->sendMessage(join('<br>', $model->getErrors()));

                    $message .= '[FAIL]';
                    $success = false;
                }

            } catch (Throwable $error) {
                // later
            }

        } else {
            $message = 'Framework not found in database';
        }

        $this->sendMessage($message);

        if ($id && !$success) {
            $success = $this->removeManual($id);
        }
        return $success;
    }

    /**
     * @param ?int $id
     *
     * @return bool
     */
    protected function removeManual(?int $id): bool
    {
        $success = true;

        if ($id) {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->delete('#__extensions')
                ->where('id = ' . $id);

            $db->setQuery($query)->execute();
            $rows = $db->getAffectedRows();

            if ($rows == 1) {
                $this->sendMessage('Removed Framework manually');

            } elseif ($rows > 1) {
                $this->sendMessage('Removed Framework, but received unexpected response from database');

            } else {
                $this->sendMessage('Unable to manually remove Framework. ID = ' . $id);
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Make sure all the framework files are deleted
     *
     * @return bool
     */
    protected function clearFolder(): bool
    {
        $success = true;

        $path = JPATH_LIBRARIES . '/allediaframework';
        if (is_dir($path)) {
            $message = 'Removing Framework library files ';

            try {
                $success = (bool)$this->removeFolder($path);

            } catch (Throwable $error) {
                $success = false;
            }

            $message .= $success ? '[OK]' : '[FAIL]';

        } else {
            $message = 'Framework files were not found';
        }

        $this->sendMessage($message);

        return $success;
    }

    /**
     * @return ?string
     */
    protected function getDownloadUrl(): ?string
    {
        $url = 'https://deploy.ostraining.com/client/update/free/stable/lib_allediaframework';

        try {
            libxml_use_internal_errors(true);

            $http = HttpFactory::getHttp();

            $updateManifest = simplexml_load_string($http->get($url)->body);
            $errors         = libxml_get_errors();
            libxml_clear_errors();
            if ($errors) {
                throw new Exception(join('<br>', $errors));
            }

            $url = $updateManifest->xpath('//downloads/downloadurl');

            return (string)array_shift($url);

        } catch (Throwable $error) {
            $this->sendMessage('Unable to find download url for Framework');
            $this->sendMessage($error);
        }

        return null;
    }

    /**
     * @return void
     */
    protected function reinstall()
    {
        $message = 'Attempting to reinstall Framework ';

        $url        = $this->getDownloadUrl();
        $tempFolder = $this->app->get('tmp_path');
        $sourceDir  = $tempFolder . '/' . basename($url);
        $zipFile    = $sourceDir . '.zip';

        if (is_file($zipFile)) {
            unlink($zipFile);
        }
        if (is_dir($sourceDir)) {
            $this->removeFolder($sourceDir);
        }

        if (File::write($zipFile, file_get_contents($url))) {
            $archive = new Archive();
            if ($archive->extract($zipFile, $sourceDir)) {
                $installer = new Joomla\CMS\Installer\Installer();
                if ($installer->install($sourceDir)) {
                    $message .= '[OK]';
                    $this->app->enqueueMessage('Reinstalling the library was successful.');
                }

            } else {
                $message .= '[FAIL]';
            }

        } else {
            $message .= '[FAIL]';
        }

        if (is_file($zipFile)) {
            unlink($zipFile);
        }
        if (is_dir($sourceDir)) {
            $this->removeFolder($sourceDir);
        }

        $this->sendMessage($message);
    }

    /**
     * @param ?string $path
     *
     * @return int
     */
    protected function removeFolder(?string $path): int
    {
        $filesAffected = 0;

        if ($path && is_dir($path)) {
            $dir   = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());

                } else {
                    unlink($file->getRealPath());
                }
                $filesAffected++;
            }

            rmdir($path);
        }

        return $filesAffected;
    }
}
