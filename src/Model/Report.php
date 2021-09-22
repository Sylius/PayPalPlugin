<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Model;

final class Report
{
    private string $content;

    private string $fileName;

    public function __construct(string $content, string $fileName)
    {
        $this->content = $content;
        $this->fileName = $fileName;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function fileName(): string
    {
        return $this->fileName;
    }
}
