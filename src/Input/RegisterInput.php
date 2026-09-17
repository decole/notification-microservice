<?php

declare(strict_types=1);

namespace App\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class RegisterInput
{
    #[Assert\Type('string')]
    #[Assert\Length(max: 255)]
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9_\-\.\@\s]+$/',
        message: 'Invalid username',
    )]
    public ?string $username = null;
}
