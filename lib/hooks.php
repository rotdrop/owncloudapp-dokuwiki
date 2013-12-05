<?php
/**
 * Copyright (c) 2012 Sam Tuke <samtuke@owncloud.com> and Martin Schulte 
 * <lebowski[at]corvus[dot]uberspace[dot]de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

/* 
 * This file is based on the file hooks.php from ownClouds version app 
 * (apps/files_versions/lib/hook.php), which was written by Sam Tuke 
 * <samtuke@owncloud.com>. It's modified for the needs of the dokuwiki-app
 *
 * Basically, the hooks deny deleting files in the wiki-folder because
 * that would lead to broken links in the wiki. There are some
 * execptions to be able to delete upload relicts and other temporary
 * files.
 *
 * The hooks leave any action alone which does not affect the wiki-folder.
 *
 */

/**
 * This class contains all hooks.
 */

namespace OCA\DokuWiki;

require_once('utils.php');

class Hooks {


	/**
	 * listen to write event.
	 */
	public static function pre_write_hook($params) {
		$path = $params[\OC\Files\Filesystem::signal_param_path];

                if (!inWiki($path)) {
                        return;
                }
		// Do we've a valid filename (no spaces, etc.)
		$filename = basename($path);
		$specialFile = allowedFilenameIfNotCleandID($filename);
		if(!$specialFile && cleanID($filename) != $filename){
                        $params['run'] = false;
		} else {
                  Storage::store($path);
                }
	}	
	
	public static function post_write_hook($params) {
		global $conf;
		global $wiki;
		$path = $params[\OC\Files\Filesystem::signal_param_path];

                if (!inWiki($path)) {
                        return;
                }

		// Do we've a valid filename (no spaces, etc.)
		$filename = basename($path);
		$dir = dirname($path);
		$specialFile = allowedFilenameIfNotCleandID($filename);
		
		if($specialFile){
			require_once('dokuwiki/lib/helper.php');
			if($pos = strrpos($filename, '.')){
				$name = substr($filename, 0, $pos);
				$ext = substr($filename, $pos);
			}else{
				$name = $filename;
				$ext = '';
			}
			// Find (nr)
			$pos = strrpos($name, ' ');
			$oldname = substr($filename, 0, $pos).$ext;
			$newname = buildNotExistingFileNameWithoutSpaces($dir, $oldname);
			$newname = \OC\Files\Filesystem::normalizePath($newname);
			\OC\Files\Filesystem::rename($path, $newname);
		}else{		
                        Storage::addMediaMetaEntry(0,'','', \OCP\User::getUser(),$path);
		}		
        }
	
	
	/**
	 * @brief Erase versions of deleted file
	 * @param array
	 *
	 * This function is connected to the delete signal of OC_Filesystem
	 * cleanup the versions directory if the actual file gets deleted
	 */
	public static function pre_remove_hook($params) {
		$path = $params[\OC\Files\Filesystem::signal_param_path];
		//prevent deleting files inside wiki-folder. Always from mediamanager
		global $wiki;

                if (!inWiki($path)) {
                        return;
                }

		$filename = basename($path);
                require_once('dokuwiki/lib/helper.php');
                if(!isEmptyDir($path) && !fileAllowedToRemove($filename)) {
                        $params['run'] = false;
                }
	}

	/**
	 * @brief rename/move versions of renamed/moved files
	 * @param array with oldpath and newpath
	 *
	 * This function is connected to the rename signal of OC_Filesystem and adjust the name and location
	 * of the stored versions along the actual file.
         *
         * Files may only be renamed if either both paths reside
         * outside the wiki-folder or both parts reside inside the
         * wiki-folder, or the old path resides outside the
         * wiki-folder and the new path inside.
         *
         * If the old path belongs to the wiki, then the
         * wiki-versioning system is used to create a backup
         * copy. Storage::rename() takes care of that.
	 */
	public static function pre_rename_hook($params) {
		$oldpath = $params[\OC\Files\Filesystem::signal_param_oldpath];
		$newpath = $params[\OC\Files\Filesystem::signal_param_newpath];

		// Do we've a valid filename (no spaces, etc.)
		$filename = basename($newpath);

		if($oldpath == $wiki || $oldpath == '/'.$wiki) {
                        // disallow renaming of wiki folder itself
			 $params['run'] = false;
		}elseif(inWiki($oldpath) && !inWiki($newpath)){
                        // renaming to a name outside wiki folder would change the file id
			$params['run'] = false;
                }elseif(inWiki($newPath) && cleanID($filename) != $filename){
                        // files inside the wiki folder are kept "wiki-clean"
			$params['run'] = false;
                }elseif(inWiki($oldpath)) {
                        Storage::rename($oldpath,$newpath,false);
                }        
	}
	
	/**This hook is called after the files were successfully
         * renamed. In this case we may need to update some
         * meta-information.
         */
	public static function post_rename_hook($params) {
		$oldpath = $params[\OC\Files\Filesystem::signal_param_oldpath];
		$newpath = $params[\OC\Files\Filesystem::signal_param_newpath];

                if (!inWiki($newpath)) {
                        return;
                }
                Storage::rename($oldpath,$newpath,true);
	}

}
