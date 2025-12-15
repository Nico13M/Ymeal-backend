<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class UserEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'required' => false,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Email(),
                ],
            ])
            ->add('firstname', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => 100]),
                ],
            ])
            ->add('lastname', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => 100]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'csrf_protection' => false,       // Les routes API gèrent CSRF via header
            'allow_extra_fields' => true,     // Permet d’ignorer des champs envoyés par le front
        ]);
    }
}
