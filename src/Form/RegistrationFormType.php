<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<User>
 */
final class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'form.user.first_name', 'empty_data' => ''])
            ->add('lastName', TextType::class, ['label' => 'form.user.last_name', 'empty_data' => ''])
            ->add('email', EmailType::class, ['label' => 'form.user.email', 'empty_data' => ''])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'form.user.password',
                    'attr' => ['autocomplete' => 'new-password'],
                    'help' => 'form.user.password_help',
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
            ])
            ->add('studioOwner', CheckboxType::class, [
                'label' => 'form.user.studio_owner',
                'help' => 'form.user.studio_owner_help',
                'mapped' => false,
                'required' => false,
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'form.user.agree_terms',
                'mapped' => false,
                'constraints' => [
                    new Assert\IsTrue(message: 'user.terms.required'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
