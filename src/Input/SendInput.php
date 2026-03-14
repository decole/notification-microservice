<?php

declare(strict_types=1);

namespace App\Input;

use Symfony\Component\Validator\Constraints as Assert;

class SendInput
{
    #[Assert\Type('string')]
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 255)]
    public string $topic;

    #[Assert\Type('string')]
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 4096)]
    public string $message;
}
