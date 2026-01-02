<?php

declare(strict_types=1);

/**
 * CSP Service for arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Service;

use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;

/**
 * Centralized CSP policy management for ArbeitszeitCheck
 */
class CSPService
{
    /**
     * Base policy shared by all contexts (no external CDNs)
     */
    public function getDefaultPolicy(): ContentSecurityPolicy
    {
        $policy = new ContentSecurityPolicy();

        // Scripts, styles, images, fonts, media, and connections from self
        $policy->addAllowedScriptDomain("'self'");
        $policy->addAllowedStyleDomain("'self'");
        $policy->addAllowedImageDomain("'self'");
        $policy->addAllowedFontDomain("'self'");
        $policy->addAllowedMediaDomain("'self'");
        $policy->addAllowedConnectDomain("'self'");

        // Allow data/blob where commonly needed
        $policy->addAllowedImageDomain('data:');
        $policy->addAllowedImageDomain('blob:');
        $policy->addAllowedFontDomain('data:');
        $policy->addAllowedMediaDomain('blob:');

        // Clickjacking protection (allow framing by self only)
        $policy->addAllowedFrameAncestorDomain("'self'");

        return $policy;
    }

    public function getMainAppPolicy(): ContentSecurityPolicy
    {
        return $this->getDefaultPolicy();
    }

    public function getModalPolicy(): ContentSecurityPolicy
    {
        return $this->getDefaultPolicy();
    }

    public function getGuestPolicy(): ContentSecurityPolicy
    {
        return $this->getDefaultPolicy();
    }

    public function getAdminPolicy(): ContentSecurityPolicy
    {
        return $this->getDefaultPolicy();
    }

    /**
     * Apply CSP and inject a template nonce parameter.
     * Note: Core middleware will attach the CSP header and JS nonce as needed.
     * Also sets additional security headers for enhanced protection.
     * 
     * IMPORTANT: We do NOT override Nextcloud's default CSP policy here.
     * Nextcloud core manages the CSP policy and handles merging app policies.
     * We only ensure the nonce is available to templates.
     * 
     * Setting our own CSP policy would override Nextcloud's default policy which
     * may allow resources that core or themes need (like fonts from themes).
     */
    public function applyPolicyWithNonce(TemplateResponse $response, string $context): TemplateResponse
    {
        // Expose nonce to templates that use inline tags
        // This is the main thing we need - the CSP policy is handled by Nextcloud core
        $params = $response->getParams();
        $params['cspNonce'] = \OC::$server->getContentSecurityPolicyNonceManager()->getNonce();
        $response->setParams($params);

        // DO NOT set a CSP policy here - let Nextcloud core handle it
        // Nextcloud's default CSP policy already handles:
        // - Allowing resources that core needs (fonts, scripts, etc.)
        // - Blocking unsafe operations (eval, etc.) where appropriate
        // - Merging policies from different apps
        
        // Add additional security headers
        // Note: Nextcloud core already sets these via .htaccess and Response::getHeaders(),
        // but we set them explicitly here to ensure they're present even if core defaults change
        $response->addHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->addHeader('X-Content-Type-Options', 'nosniff');
        $response->addHeader('X-XSS-Protection', '1; mode=block');
        $response->addHeader('Referrer-Policy', 'no-referrer');
        
        // Strict-Transport-Security should be set by web server for HTTPS
        // We don't set it here as it should be configured at server level
        
        return $response;
    }
}
