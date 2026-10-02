<?php

namespace App\Services;

use RuntimeException;

class LdapAuthenticator
{
    public function authenticate(string $buasriId, string $password): bool
    {
        if (! extension_loaded('ldap')) {
            throw new RuntimeException('PHP LDAP extension is not enabled.');
        }

        $connection = ldap_connect(
            (string) config('course-workflow.ldap.url'),
            (int) config('course-workflow.ldap.port', 389),
        );

        if ($connection === false) {
            throw new RuntimeException('Unable to connect to the SWU LDAP server.');
        }

        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, (int) config('course-workflow.ldap.timeout', 5));

        try {
            $distinguishedName = sprintf((string) config('course-workflow.ldap.user_dn'), $buasriId);

            return @ldap_bind($connection, $distinguishedName, $password);
        } finally {
            ldap_unbind($connection);
        }
    }
}
