<?php
/**
 * rep2 - Ajax
 * ファイルアップローダー
 */

require_once __DIR__ . '/../init.php';

// {{{ P2UploaderInterface

interface P2UploaderInterface
{
    /**
     * @param string $localPath
     * @param string $filename
     *
     * @return string URL
     */
    public function upload($localPath, $filename);
}

// }}}
// {{{ P2DropboxUploader

class P2DropboxUploader implements P2UploaderInterface
{
    /**
     * @var Dropbox\Client
     */
    private $client;

    /**
     * @var string
     */
    private $prefix;

    /**
     * @param string $authJsonFile
     * @param string $clientIdentifier
     * @param string $prefix
     */
    public function __construct($authJsonFile, $clientIdentifier, $prefix)
    {
        $pathError = Dropbox\Path::findError($prefix . 'check');
        if ($pathError !== null) {
            throw new RuntimeException("Dropbox upload prefix error: {$pathError}");
        }

        list($appInfo, $accessToken) = Dropbox\AuthInfo::loadFromJsonFile($authJsonFile);
        $config = new Dropbox\Config($appInfo, $clientIdentifier);
        $this->client = new Dropbox\Client($config, $accessToken);
        $this->prefix = sprintf('%s%x', $prefix, time());
    }

    /**
     * @param string $localPath
     * @param string $filename
     *
     * @return string URL
     */
    public function upload($localPath, $filename)
    {
        $size = @getimagesize($localPath);
        if ($size) {
            $extension = image_type_to_extension($size[2]);
        } else {
            $extension = strrchr($filename, '.');
        }

        $metadata = $this->client->uploadFile(
            $this->prefix . hash_file('crc32b', $localPath) . $extension,
            Dropbox\WriteMode::add(),
            fopen($localPath, 'rb'),
            filesize($localPath)
        );

        if (is_array($metadata) && isset($metadata['path'])) {
            //return $this->client->createShareableLink($metadata['path']);
            $data = $this->client->createTemporaryDirectLink($metadata['path']);
            if (is_array($data)) {
                return $data[0];
            }
        }

        return null;
    }
}

// }}}
// {{{ handle_uploaded_file()

/**
 * @param P2UploaderInterface $uploader
 * @param array $file
 *
 * @return string URL
 */
function handle_uploaded_file(P2UploaderInterface $uploader, array $file)
{
    if ($file['error'] !== UPLOAD_ERR_OK
        || !file_exists($file['tmp_name'])
        || filesize($file['tmp_name']) !== $file['size']) {
        throw new RuntimeException("failed to upload file '{$file['name']}'.");
    }

    return $uploader->upload($file['tmp_name'], $file['name']);
}

// }}}
// {{{ メインルーチン

$result = array('urls' => array());
$error = '';

ob_start();

// {{{ アップローダーをセットアップ

try {
    $uploader = new P2DropboxUploader(
        $_conf['dropbox_auth_json'],
        $_conf['p2name'],
        $_conf['expack.dropbox.upload_prefix']
    );
} catch (Exception $e) {
    $uploader = null;
    $error .= $e->getMessage() . "\n";
}

// }}}

if (!$uploader) {
    // アップローダーのセットアップ失敗
} elseif (!isset($_GET['token'], $_SESSION['upload_token'])
    || $_GET['token'] !== $_SESSION['upload_token']) {
    // CSRFトークン不一致
    $result['error'] = "invalid token.\n";
} elseif (!isset($_FILES['upload']['name'])) {
    // ファイルなし
    $result['error'] = "no files.\n";
} elseif (is_array($_FILES['upload']['name'])) {
    // {{{ マルチファイルアップロード

    $fileCount = count($_FILES['upload']['name']);
    $keys = array('name', 'tmp_name', 'type', 'error', 'size');
    for ($index = 0; $index < $fileCount; $index++) {
        $file = array();
        foreach ($keys as $key) {
            if (isset($_FILES['upload'][$key][$index])) {
                $file[$key] = $_FILES['upload'][$key][$index];
            } else {
                $error .= "file #{$index} is not valid.\n";
                continue;
            }
        }
        try {
            $url = handle_uploaded_file($uploader, $file);
            if ($url) {
                $result['urls'][] = $url;
            }
        } catch (Exception $e) {
            $error .= $e->getMessage() . "\n";
        }
    }

    // }}}
} else {
    // {{{ 単体ファイルアップロード

    try {
        $url = handle_uploaded_file($uploader, $_FILES['upload']);
        if ($url) {
            $result['urls'][] = $url;
        }
    } catch (Exception $e) {
        $error .= $e->getMessage() . "\n";
    }

    // }}}
}

if (strlen($error)) {
    $result['error'] = rtrim($error);
}
$error .= ob_get_clean();
if (strlen($error)) {
    error_log($error);
}

// }}}
// {{{ 出力

header('Content-Type: application/json');
mb_convert_variables('UTF-8', 'SJIS-win', $result);
$json = json_encode($result);
//error_log($json);
echo $json;

// }}}

/*
 * Local Variables:
 * mode: php
 * coding: cp932
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: nil
 * End:
 */
// vim: set syn=php fenc=cp932 ai et ts=4 sw=4 sts=4 fdm=marker:
