<?php

namespace KarmaDev\Panel\Client;

use KarmaDev\Panel\Configuration;

use Tx\Mailer as Email;

class Mailer {

    private string $target;
    private Email $email;

    public function __construct(string $target) {
        $this->target = $target;
    }

    public function send(string $subject, string $template, array $params) {
        if (!str_ends_with($this->target, 'karmadev.es')) {
            $config = new Configuration();

            $email_content = file_get_contents($config->getWorkingDirectory() . 'vendor/emails/' . $template . '.html');
            foreach ($params as $key => $value) {
                $email_content = str_replace('%' . $key . '%', $value, $email_content);
            }

            $ok = (new Email())
                ->setServer($config->getMailerHost(), $config->getMailerPort())
                ->setAuth($config->getMailerAccount(), $config->getMailerPassword())
                ->setFrom('KarmaDevPanel', $config->getMailerAccount())
                ->setFakeFrom('KarmaDevPanel', $config->getDisplayAccount())
                ->addTo($params['username'], $this->target)
                ->setSubject($subject)
                ->setBody($email_content)
                ->send();

            return $ok;
        }

        return true;
    }
}