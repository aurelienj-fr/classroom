<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Changes the password of the connected member; the current password is required.
 *
 * @extends AbstractType<array{currentPassword: string, newPassword: string}>
 */
final class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'form.user.current_password',
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [
                    new Assert\NotBlank(message: 'user.password.blank'),
                    new UserPassword(message: 'user.password.wrong_current'),
                ],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'form.user.new_password',
                    'help' => 'form.user.password_help',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'form.user.password_confirm',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'user.password.mismatch',
                'constraints' => [
                    new Assert\NotBlank(message: 'user.password.blank'),
                    new Assert\Length(min: 12, max: 4096, minMessage: 'user.password.too_short'),
                    new Assert\NotCompromisedPassword(message: 'user.password.compromised', skipOnError: true),
                ],
            ]);
    }
}
