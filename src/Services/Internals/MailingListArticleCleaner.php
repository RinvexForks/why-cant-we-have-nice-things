<?php
namespace History\Services\Internals;

use Illuminate\Support\Arr;

class MailingListArticleCleaner
{
    /**
     * @var string
     */
    protected $charset = '';

    /**
     * @var array
     */
    protected $headers = [];

    /**
     * @var bool
     */
    protected $inHeaders = true;

    /**
     * @var bool
     */
    protected $inSignature = false;

    /**
     * @var int
     */
    protected $mimecount = 0;

    /**
     * @var string
     */
    protected $headerKey = '';

    /**
     * @var string
     */
    protected $boundary = '';

    /**
     * @var array
     */
    protected $boundaries = [];

    /**
     * @var string
     */
    protected $encoding = '';

    /**
     * @var string
     */
    protected $mimetype = '';

    /**
     * @param array $lines
     *
     * @return string
     */
    public function cleanup($lines)
    {
        $contents = '';
        foreach ($lines as $line) {

            // Skip signature and boundaries
            if (
                $this->inSignature ||
                (substr($line, 0, 2) === '--' && !$this->isSignatureBeginning($line))
            ) {
                continue;
            }

            // If we're not reading text, fuck it
            if (strlen($this->mimetype) && $this->mimetype !== 'text/plain') {
                continue;
            }

            // If we're in the headers, read them
            if ($this->inHeaders) {
                $this->extractHeaders($line);
                continue;
            }

            // If we just got the signature, stop reading
            if ($this->isSignatureBeginning($line)) {
                $this->inSignature = true;
                continue;
            }

            $contents .= PHP_EOL.$this->convertLineWithEncoding($line);
        }

        return trim($contents, "\n\r\t ");
    }

    /**
     * Extract the message's headers
     *
     * @param string $line
     */
    protected function extractHeaders($line)
    {
        // If we were in the headers and face
        // a newline, that means we're not in Kansas anymore
        if ($line === null || $line === "" || $line === "\n" || $line == "\r\n") {
            $this->inHeaders = false;
            $this->configureEncoding();

            return;
        }

        // Header fields can be split across lines: CRLF WSP where WSP
        // is a space (ASCII 32) or tab (ASCII 9)
        $firstCharacter = substr($line, 0, 1);
        if ($this->headerKey && ($firstCharacter == ' ' || $firstCharacter == "\t")) {
            $this->headers[$this->headerKey] .= $line;

            return;
        }

        @list($key, $value) = explode(": ", $line, 2);
        if ($key && $value) {
            $this->headerKey                 = strtolower($key);
            $this->headers[$this->headerKey] = $value;
        }
    }

    /**
     * Configure the message's encoding from its headers
     */
    protected function configureEncoding()
    {
        // Extract various informations from the Content-Type
        if (isset($this->headers['content-type'])) {
            if (preg_match('/charset=(["\']?)([\w-]+)\1/i', $this->headers['content-type'], $matches)) {
                $this->charset = trim($matches[2]);
            }

            if (preg_match('/boundary=(["\']?)(.+)\1/is', $this->headers['content-type'], $matches)) {
                $this->boundaries[] = trim($matches[2]);
                $this->boundary     = end($this->boundaries);
            }

            if (preg_match("/([^;]+)(;|\$)/", $this->headers['content-type'], $matches)) {
                $this->mimetype = trim(strtolower($matches[1]));
                ++$this->mimecount;
            }
        }

        // Save encoding for later
        $encoding       = Arr::get($this->headers, 'content-transfer-encoding');
        $this->encoding = strtolower(trim($encoding));
    }

    /**
     * Convert a line to the correct encoding found
     * in the headers
     *
     * @param string $line
     *
     * @return string
     */
    protected function convertLineWithEncoding($line)
    {
        // Convert line based on the encoding we found
        switch ($this->encoding) {
            case "quoted-printable":
                $line = quoted_printable_decode($line);
                break;
            case "base64":
                $line = base64_decode($line);
                break;
        }

        // we can't convert it to UTF, because cvs commits don't have charset info
        // so its preferable to leave it as-is, and let users choose the correct charset
        // in their browser. this is specially important for php.doc.* groups
        if ($this->charset && strpos(strtolower($this->charset), 'utf-8') === false) {
            $line = $this->convertToUtf8($line, $this->charset);
        }

        return $line;
    }

    /**
     * Convert a string to UTF8 in a particular charset
     *
     * @param string $string
     * @param string $charset
     *
     * @return string
     */
    protected function convertToUtf8($string, $charset)
    {
        $converted = iconv($charset ? $charset : 'iso-8859-1', 'utf-8', $string);
        if ($converted === false) {
            return $string;
        }

        return $converted;
    }

    /**
     * @param string $line
     *
     * @return bool
     */
    private function isSignatureBeginning($line)
    {
        return $line === '-- ';
    }
}
