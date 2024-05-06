<?php

/**
 * @author Jakub Lech <info@smartbyte.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spinbits\SyliusBaselinkerPlugin\Handler;

use Spinbits\SyliusBaselinkerPlugin\Rest\Input;

class FileVersionActionHandler implements HandlerInterface
{
    public function handle(Input $input): array
    {
        return [
            'platform' => "OpenCart",
            'version' => "4.2.12",
            'standard' => 4,
        ];
    }
}
