<?php

namespace App\Console\Commands;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverWait;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Dusk\Chrome\ChromeProcess;

#[Signature('facebook:login {--timeout=120 : Max seconds to wait for login}')]
#[Description('Open a Chrome window to log into Facebook and capture session cookies for the marketplace scraper')]
class FacebookLoginCommand extends Command
{
    public function handle(): int
    {
        $this->info('Starting ChromeDriver...');

        $process = (new ChromeProcess)->toProcess(['--port=9515']);
        $process->start();

        // Wait for ChromeDriver to be ready
        sleep(2);

        if (! $process->isRunning()) {
            $this->error('Failed to start ChromeDriver.');
            $this->line($process->getErrorOutput());

            return self::FAILURE;
        }

        $this->info('ChromeDriver started.');

        $driver = null;

        try {
            // Open a VISIBLE Chrome window (no headless) so the user can log in
            $options = (new ChromeOptions)->addArguments([
                '--window-size=1280,900',
                '--disable-search-engine-choice-screen',
                '--lang=pt-BR',
            ]);

            $options->setExperimentalOption('excludeSwitches', ['enable-automation']);

            $capabilities = DesiredCapabilities::chrome();
            $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

            $driver = RemoteWebDriver::create('http://localhost:9515', $capabilities);

            $driver->get('https://www.facebook.com/login');

            $this->newLine();
            $this->info('A Chrome window has opened. Please log into your Facebook account.');
            $this->info('After logging in, navigate to:');
            $this->line('  https://www.facebook.com/marketplace/fortaleza/');
            $this->newLine();
            $this->warn('Waiting for you to complete login...');
            $this->line('(Timeout: ' . $this->option('timeout') . ' seconds)');

            $timeout = (int) $this->option('timeout');
            $wait = new WebDriverWait($driver, $timeout, 2000);

            try {
                $wait->until(function (RemoteWebDriver $driver) {
                    $url = $driver->getCurrentURL();

                    // User has navigated away from login page
                    if (str_contains($url, '/marketplace')) {
                        return true;
                    }

                    // Check for the presence of the user menu (logged in state)
                    $elements = $driver->findElements(WebDriverBy::cssSelector('[aria-label="Your profile"], [aria-label="Seu perfil"]'));

                    return count($elements) > 0;
                });
            } catch (\Throwable) {
                // Timeout — check if we're at least on facebook.com (not login)
                $currentUrl = $driver->getCurrentURL();

                if (str_contains($currentUrl, '/login')) {
                    $this->error('Login timed out. Please try again.');
                    $driver->quit();
                    $process->stop();

                    return self::FAILURE;
                }
            }

            // Navigate to marketplace to ensure marketplace cookies are set
            $this->info('Navigating to Marketplace...');
            $driver->get('https://www.facebook.com/marketplace/fortaleza/');
            sleep(3);

            // Extract all cookies
            $cookies = $driver->manage()->getCookies();

            $cookieData = array_map(fn ($cookie) => [
                'name' => $cookie->getName(),
                'value' => $cookie->getValue(),
                'domain' => $cookie->getDomain(),
                'path' => $cookie->getPath(),
                'expiry' => $cookie->getExpiry(),
                'secure' => $cookie->isSecure(),
                'httpOnly' => $cookie->isHttpOnly(),
            ], $cookies);

            $cookieFile = storage_path('app/facebook_cookies.json');
            file_put_contents($cookieFile, json_encode($cookieData, JSON_PRETTY_PRINT));

            $this->newLine();
            $this->info('Login successful! Saved ' . count($cookieData) . ' cookies.');
            $this->line('Cookie file: ' . $cookieFile);
            $this->newLine();
            $this->info('You can now run: php artisan scrape facebook --term="playstation 4" --sync');

            $driver->quit();
            $process->stop();

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());

            if ($driver !== null) {
                try {
                    $driver->quit();
                } catch (\Throwable) {
                    // Ignore
                }
            }

            $process->stop();

            return self::FAILURE;
        }
    }
}
