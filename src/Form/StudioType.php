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
            ->add('name', TextType::class, ['label' => 'Nom du studio', 'empty_data' => ''])
            ->add('description', TextareaType::class, [
                'label' => 'Présentation',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('disciplines', EntityType::class, [
                'label' => 'Disciplines proposées',
                'class' => Discipline::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'query_builder' => static fn (EntityRepository $repository): QueryBuilder => $repository
                    ->createQueryBuilder('d')
                    ->orderBy('d.name', 'ASC'),
            ])
            ->add('address', TextType::class, ['label' => 'Adresse', 'empty_data' => ''])
            ->add('postalCode', TextType::class, ['label' => 'Code postal', 'empty_data' => ''])
            ->add('city', TextType::class, ['label' => 'Ville', 'empty_data' => ''])
            ->add('phone', TelType::class, ['label' => 'Téléphone', 'required' => false])
            ->add('email', EmailType::class, ['label' => 'E-mail de contact', 'required' => false])
            ->add('cancellationDeadlineHours', IntegerType::class, [
                'label' => 'Délai d\'annulation (heures)',
                'empty_data' => '0',
                'help' => 'Les membres peuvent annuler jusqu\'à ce délai avant le début de la séance.',
                'attr' => ['min' => 0, 'max' => 168],
            ])
            ->add('published', CheckboxType::class, [
                'label' => 'Publier le studio (visible et réservable par le public)',
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
