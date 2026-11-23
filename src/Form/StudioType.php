<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Discipline;
use App\Entity\Studio;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Studio>
 */
final class StudioType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'form.studio.name', 'empty_data' => ''])
            ->add('description', TextareaType::class, [
                'label' => 'form.studio.description',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('disciplines', EntityType::class, [
                'label' => 'form.studio.disciplines',
                'class' => Discipline::class,
                'choice_label' => static fn (Discipline $discipline): Discipline => $discipline,
                'multiple' => true,
                'expanded' => true,
                'query_builder' => static fn (EntityRepository $repository): QueryBuilder => $repository
                    ->createQueryBuilder('d')
                    ->orderBy('d.name', 'ASC'),
            ])
            ->add('address', TextType::class, ['label' => 'form.studio.address', 'empty_data' => ''])
            ->add('postalCode', TextType::class, ['label' => 'form.studio.postal_code', 'empty_data' => ''])
            ->add('city', TextType::class, ['label' => 'form.studio.city', 'empty_data' => ''])
            ->add('phone', TelType::class, ['label' => 'form.studio.phone', 'required' => false])
            ->add('email', EmailType::class, ['label' => 'form.studio.email', 'required' => false])
            ->add('cancellationDeadlineHours', IntegerType::class, [
                'label' => 'form.studio.cancellation_deadline',
                'empty_data' => '0',
                'help' => 'form.studio.cancellation_deadline_help',
                'attr' => ['min' => 0, 'max' => 168],
            ])
            ->add('published', CheckboxType::class, [
                'label' => 'form.studio.published',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Studio::class,
            'empty_data' => null,
        ]);
    }
}
