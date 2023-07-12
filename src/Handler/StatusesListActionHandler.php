<?php

/**
 * @author Adam Terepora <adam@terepora.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spinbits\SyliusBaselinkerPlugin\Handler;

use Spinbits\SyliusBaselinkerPlugin\Rest\Input;
use Symfony\Contracts\Translation\TranslatorInterface;

class StatusesListActionHandler implements HandlerInterface
{
	const SYLIUS_ORDER_CONFIG_STATE_KEY = 'sylius_order';

    protected TranslatorInterface $translator;

    /** @var array */
    protected $config;

    public function __construct(TranslatorInterface $translator, array $config)
    {
        $this->translator = $translator;
        $this->config = $config;
    }

    public function handle(Input $input): array
    {
        if (!array_key_exists(self::SYLIUS_ORDER_CONFIG_STATE_KEY, $this->config)) {
            throw new \RuntimeException("The provided state machine key is not configured.");
        }
        $states = $this->config[self::SYLIUS_ORDER_CONFIG_STATE_KEY]['states'];
        $statesResponse = [];
        foreach ($states as $state) {
            $statesResponse[$state] = $this->translator->trans(sprintf('sylius.ui.%s', $state));
        }
        return $statesResponse;
    }
}
