<?php
namespace Wainwright\CasinoDog\Middleware;
use Closure;

class SecureHeaders
{
    // Enumerate headers which you do not want in your application's responses.
    // Great starting point would be to go check out @Scott_Helme's:
    // https://securityheaders.com/
    private $unwantedHeaderList = [
        'X-Powered-By',
        'Server',
    ];

    private $whitelist = [
        'coolio.com',
        'localhost',
        '*.coolio.com',
    ];

    public function whitelistedDomains() 
        {
            $whitelist = $this->whitelist;
            array_push($whitelist, config('casino-dog.hostname'), "*.".config('casino-dog.hostname'));
            return $this->listDomainsSpacedOut($whitelist);        
        }

    public function handle($request, Closure $next)
    {
        $path = request()->path();
        if($path[0] === '_') {
            return redirect('/');
        }
        if($path[0] === '.') {
            return redirect(config('casino-dog.domain'));
        }
        
        if(!str_contains($path, 'allseeingdavid')) {
                return $next($request);
        }
        $this->removeUnwantedHeaders($this->unwantedHeaderList);
        $response = $next($request);
            $response->headers->set('Referrer-Policy', 'no-referrer-when-downgrade');
            $response->headers->set('X-XSS-Protection', '1; mode=block');
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline' ".$this->whitelistedDomains()."; style-src 'self' ".$this->whitelistedDomains()." 'unsafe-inline'; img-src 'self' * data:; font-src 'self' data: ; connect-src 'self' ".$this->whitelistedDomains()."; media-src 'self'; frame-src 'self' ".$this->whitelistedDomains()."; object-src 'none'; base-uri 'self';");
            $response->headers->set('Expect-CT', 'enforce, max-age=30');
            $response->headers->set('Permissions-Policy', 'autoplay=(self), camera=(), encrypted-media=(self), fullscreen=(), geolocation=(), gyroscope=(self), magnetometer=(), microphone=(), midi=(), payment=(), sync-xhr=(self), usb=()');
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET,POST,PUT,PATCH,DELETE,OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type,Authorization,X-Requested-With,X-CSRF-Token');

        return $response;
    }
    private function removeUnwantedHeaders($headerList)
    {
        foreach ($headerList as $header)
            header_remove($header);
    }
    private function listDomainsSpacedOut($domain_array)
    {
        $global_domain = "";
        foreach ($domain_array as $domain)  {
            $global_domain = $global_domain . $domain . " ";
        }

        return $global_domain;
    }
}