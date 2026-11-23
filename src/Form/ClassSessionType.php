<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ClassSession;
use App\Schedule\SessionScheduler;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Edits a session. The activity is fixed by the controller; "weeklyRepetitions" only exists on creation.
 *
 * @extends AbstractType<ClassSession>
 */
final class ClassSessionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startsAt', DateTimeType::class, [
                'label' => 'form.session.starts_at',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'with_seconds' => false,
                // The entity requires a start date: a blank value is reported by NotNull instead of reaching the setter.
                'setter' => static function (ClassSession $session, ?\DateTimeImmutable $startsAt): void {
                    if (null !== $startsAt) {
                        $session->setStartsAt($startsAt);
                    }
                },
                'constraints' => [new Assert\NotNull(message: 'session.starts_at.required')],
            ])
            ->add('capacity', IntegerType::class, [
                'label' => 'form.session.capacity',
                'empty_data' => '0',
                'attr' => ['min' => 1, 'max' => 500],
            ])
            ->add('instructorName', TextType::class, [
                'label' => 'form.session.instructor',
                'required' => false,
            ]);

        if (true === $options['allow_repetition']) {
            $builder->add('weeklyRepetitions', IntegerType::class, [
                'label' => 'form.session.repetitions',
                'mapped' => false,
                'data' => 0,
                'help' => 'form.session.repetitions_help',
                'help_translation_parameters' => ['%max%' => SessionScheduler::MAX_WEEKLY_REPETITIONS],
                'attr' => ['min' => 0, 'max' => SessionScheduler::MAX_WEEKLY_REPETITIONS],
                'constraints' => [
                    new Assert\NotNull(),
                    new Assert\Range(min: 0, max: SessionScheduler::MAX_WEEKLY_REPETITIONS),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ClassSession::class,
            'empty_data' => null,
            'allow_repetition' => false,
        ]);
        $resolver->setAllowedTypes('allow_repetition', 'bool');
    }
}
