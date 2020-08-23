<?php
declare(strict_types=1);

namespace Sb\ImageHandler;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sb\ImageHandler\Exception\Exception;
use Sb\ImageHandler\Transformation\TransformationInterface;

class ImageHandler
{

    public const CIPHER_AES_256_CFB = 'aes-256-cfb';

    /** @var string */
    private $imageUrl;

    /** @var string */
    private $encryptedImageUrl;

    /** @var string */
    private $requestUrl;

    /** @var string */
    private $requestTransformationPattern;

    /** @var string */
    private $tmpFile;

    /** @var string */
    private $rootDir;

    /** @var string */
    private $imagemagickBin = 'convert';

    /** @var bool */
    private $useCache = true;

    /** @var string */
    private $cipher;

    /** @var string */
    private $iv;

    /** @var string */
    private $key;

    /** @var array<TransformationInterface> */
    private $transformations = [];

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $fallbackImage;

    /**
     * ImageHandler constructor.
     * @param string $rootDir
     * @param string $iv
     * @param string $key
     * @param string $cipher
     */
    public function __construct(string $rootDir, string $iv, string $key, string $cipher = self::CIPHER_AES_256_CFB)
    {
        $this->cipher = $cipher;
        $this->key = $key;
        $this->iv = base64_decode($iv);
        $this->rootDir = $rootDir;

        $this->logger = new NullLogger();
    }

    /**
     * @param LoggerInterface $logger
     * @return ImageHandler
     */
    public function setLogger(LoggerInterface $logger): ImageHandler
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @param string $fallbackImage
     * @return ImageHandler
     */
    public function setFallbackImage(string $fallbackImage): ImageHandler
    {
        $this->fallbackImage = $fallbackImage;
        return $this;
    }

    /**
     * @param string $requestUrl
     * @throws Exception
     */
    public function extractImageUrl(string $requestUrl): void
    {

        $this->logger->info('New request url: ' . $requestUrl);
        $this->logger->info('Storage dir: ' . $this->rootDir);

        $this->requestUrl = $requestUrl;

        $parts = explode('/', $requestUrl);
        if (count($parts) < 4) {
            $this->logger->info('Invalid request url');
            throw new Exception('Invalid request url');
        }
        $imageUrl = $parts[count($parts) - 1];
        $this->encryptedImageUrl = $imageUrl;

        $this->logger->info('Encrypted image url: ' . $this->encryptedImageUrl);

        $imageUrl = pathinfo($imageUrl, PATHINFO_FILENAME);
        $imageUrl = $this->decrypt($imageUrl);

        if (!parse_url($imageUrl, PHP_URL_PATH)) {
            $this->logger->error('Invalid input file ' . $imageUrl);
            throw new Exception('Invalid input file');
        }

        $this->imageUrl = $imageUrl;

        $this->logger->info('Source image url: ' . $this->imageUrl);

        $this->requestTransformationPattern = $parts[count($parts) - 4];
        $this->tmpFile = $this->rootDir . '/' . $this->encryptedImageUrl . '.tmp';

        $this->logger->info('Requested transformation pattern: ' . $this->requestTransformationPattern);
        $this->logger->info('Tmp image file: ' . $this->tmpFile);

    }

    /**
     * @param string$requestUrl
     * @param array<int, string> $additionalTransformationsPatterns
     */
    public function process(string $requestUrl, array $additionalTransformationsPatterns = []): void
    {
        try {

            $this->extractImageUrl($requestUrl);

            if (!$this->useCache && count($additionalTransformationsPatterns)) {
                $this->logger->error('Used additional transformations without cache unsupported');
                throw new Exception('Use additional transformations without cache unsupported');
            }

            $this->downloadFile();

            foreach ($additionalTransformationsPatterns as $additionalTransformationsPattern) {
                $this->processTransformation($additionalTransformationsPattern);
            }

            $destinationFile = $this->processTransformation($this->requestTransformationPattern);

            $this->sentFile($destinationFile);

        } catch (\Exception $e) {

            $this->logger->error('Process failed: ' . $e->getMessage());

            $this->failed();
        } finally {
            $this->cleanUp($this->tmpFile);
        }
    }

    /**
     * @param string $transformationPattern
     * @return string
     * @throws Exception
     */
    public function processTransformation(string $transformationPattern): string
    {

        $this->logger->info('Process transformation: ' . $transformationPattern);

        $destinationDir = $this->rootDir . '/' . $transformationPattern . '/' . $this->getPrefix($this->encryptedImageUrl);
        $destinationFile = $destinationDir . '/' . $this->encryptedImageUrl;

        if (!is_dir($destinationDir) && !mkdir($destinationDir, 0777, true) && !is_dir($destinationDir)) {
            $this->logger->error("Directory {$destinationDir} was not created");
            throw new Exception("Directory {$destinationDir} was not created");
        }

        $transformation = $this->getTransformation($transformationPattern);
        $this->convert($destinationFile, $transformation->getParams($transformationPattern));

        return $destinationFile;
    }

    /**
     * @param string $name
     * @return string
     */
    public function getPrefix(string $name): string
    {
        $length = strlen($name) - 5;
        return $name[$length-1] . '/' . $name[$length];
    }

    /**
     * @param string $url
     * @return string
     * @throws Exception
     */
    public function generateImageName(string $url): string
    {
        $urlPath = parse_url($url, PHP_URL_PATH);
        if (false === $urlPath || null === $urlPath) {
            throw new Exception('Image url invalid');
        }

        $extension = pathinfo($urlPath, PATHINFO_EXTENSION);
        $name = $this->crypt($url) . '.' . $extension;
        return $this->getPrefix($name) . '/' . $name;
    }

    /**
     * @param string $destinationFile
     * @param array<int, string> $transformationParams
     * @throws Exception
     */
    public function convert(string $destinationFile, array $transformationParams): void
    {

        if (!count($transformationParams)) {
            if (copy($this->tmpFile, $destinationFile)) {
                $this->logger->info("Copy tmp file to {$destinationFile} success");
            } else {
                $this->logger->error("Copy tmp file to {$destinationFile} failed");
            }
            return;
        }

        $command = sprintf(
            '%s %s %s %s 2>&1',
            $this->imagemagickBin,
            escapeshellarg($this->tmpFile),
            implode(' ', $transformationParams),
            escapeshellarg($destinationFile)
        );
        $result = -1;
        $output = [];

        $this->logger->info("Execute command {$command}");

        exec($command, $output, $result);

        if ($result !== 0) {
            $this->logger->error('Convert failed with message: ' . implode("\n", $output));
            throw new Exception('Convert failed with message: ' . implode("\n", $output));
        }

        $this->logger->info('Transformation success');
    }

    /**
     * @param string $destinationFile
     */
    public function sentFile(string $destinationFile): void
    {
        if ($this->useCache) {

            $this->logger->info("Redirect header to {$this->requestUrl}");

            header("Location: {$this->requestUrl}", true, 302);
        } else {

            $this->logger->info('Sent file content');

            readfile($destinationFile);

            $this->cleanUp($destinationFile);
        }
    }

    /**
     * @param string $filename
     */
    public function cleanUp(string $filename): void
    {
        if (unlink($filename)) {
            $this->logger->info("File {$filename} deleted");
        } else {
            $this->logger->error("Delete file {$filename} failed");
        }
    }

    public function failed(): void
    {
        $this->logger->info('Sent fallback image');
        if ($this->fallbackImage) {

            if (is_file($this->fallbackImage)) {
                header('Content-type: image/jpg');
                readfile($this->fallbackImage);
                return;
            }

            $this->logger->info("Fallback image {$this->fallbackImage} not found");
        }

        $this->logger->info('Sent default fallback image');

        header('Content-type: image/gif');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    }

    /**
     * @throws Exception
     */
    public function downloadFile(): void
    {
        if (!copy($this->imageUrl, $this->tmpFile)) {
            $this->logger->error("Copy {$this->imageUrl} to {$this->tmpFile} failed");
            throw new Exception("Copy {$this->imageUrl} to {$this->tmpFile} failed");
        }
        $this->logger->info('Download file success');
    }

    /**
     * @param string $data
     * @return string
     * @throws Exception
     */
    public function crypt(string $data): string
    {
        $cryptData = openssl_encrypt($data, $this->cipher, $this->key, OPENSSL_RAW_DATA, $this->iv);
        if (false === $cryptData) {
            throw new Exception('Crypt failed');
        }
        return $this->encodeBase64($cryptData);
    }

    /**
     * @param string $data
     * @return string
     * @throws Exception
     */
    public function decrypt(string $data): string
    {
        $decryptData = openssl_decrypt($this->decodeBase64($data), $this->cipher, $this->key, OPENSSL_RAW_DATA, $this->iv);
        if (false === $decryptData) {
            throw new Exception('Decrypt failed');
        }
        return $decryptData;
    }

    /**
     * @param string $data
     * @return string
     */
    public function encodeBase64(string $data): string
    {
        return rtrim(
            strtr(
                base64_encode($data),
                '+/',
                '-_'
            ),
            '='
        );
    }

    /**
     * @param string $data
     * @return string
     */
    public function decodeBase64(string $data): string
    {
        $data = strtr($data, '-_', '+/') . substr('===', (strlen($data) + 3) % 4);
        return base64_decode($data);
    }

    /**
     * @return string
     * @throws Exception
     */
    public function generateIv(): string
    {
        $ivlen = openssl_cipher_iv_length($this->cipher);
        if (false === $ivlen) {
            throw new Exception('Get iv length failed');
        }
        $iv = openssl_random_pseudo_bytes($ivlen);
        if (false === $iv) {
            throw new Exception('Get iv failed');
        }
        return base64_encode($iv);
    }

    /**
     * @param TransformationInterface $transformation
     */
    public function addTransformation(TransformationInterface $transformation): void
    {
        $this->transformations[] = $transformation;
    }

    /**
     * @param string $transformationString
     * @return TransformationInterface
     * @throws Exception
     */
    public function getTransformation(string $transformationString): TransformationInterface
    {
        /** @var TransformationInterface $transformation */
        foreach ($this->transformations as $transformation) {
            if (preg_match("#{$transformation->getPattern()}#i", $transformationString)) {
                return $transformation;
            }
        }

        $this->logger->error("Transformation {$transformationString} not found");
        throw new Exception('Transformation not found');
    }

    /**
     * @param bool $useCache
     * @return ImageHandler
     */
    public function setUseCache(bool $useCache): ImageHandler
    {
        $this->useCache = $useCache;
        return $this;
    }

}