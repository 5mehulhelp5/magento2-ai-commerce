<?php
declare(strict_types=1);

namespace Selectika\AiCommerce\Model;

class FeedAuthorization
{
    /**
     * The Authorization: Bearer header is preferred; a `token` query parameter is accepted only when the
     * request carries no Bearer header. Query tokens end up in access logs and browser history, so use
     * them only for clients that cannot set headers.
     */
    public function accepts(string $authorization, string $token, bool $secure, ?string $queryToken = null): bool
    {
        if (!$secure || preg_match('/^[A-Za-z0-9_-]{32,128}$/D', $token) !== 1) {
            return false;
        }
        $presented = $this->presented($authorization, $queryToken);
        return $presented !== null && hash_equals($token, $presented);
    }

    public function usesQueryToken(string $authorization, ?string $queryToken): bool
    {
        return !str_starts_with($authorization, 'Bearer ') && $queryToken !== null && $queryToken !== '';
    }

    private function presented(string $authorization, ?string $queryToken): ?string
    {
        if (str_starts_with($authorization, 'Bearer ')) {
            return preg_match('/^Bearer ([A-Za-z0-9_-]{32,128})$/D', $authorization, $match) === 1 ? $match[1] : null;
        }
        return $queryToken !== null && preg_match('/^[A-Za-z0-9_-]{32,128}$/D', $queryToken) === 1 ? $queryToken : null;
    }
}
