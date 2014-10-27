BaseBundle
==========

A base bundle for my own bundles, use it at your **own risk**.

## Step 1

Install the bundle using composer

    "mayeco/base-bundle": "1.0.*@dev"

## Step 2

Enable the Bundle in your Kernel

    new Mayeco\BaseBundle\BaseBundle()

## Step 3

Extend your controllers

    class MyAcmeController extends \Mayeco\BaseBundle\Controller\Controller
