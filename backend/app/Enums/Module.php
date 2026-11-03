<?php

namespace App\Enums;

/** The gym-side modules an owner can grant, taken from the spec's seven modules. */
enum Module: string
{
    case Hr = 'hr'; // HR and employee management, including roles (Module 5A)
    case GymSetup = 'gym_setup';
    case Training = 'training';
    case Members = 'members';
    case HealthRecords = 'health_records';
    case Payroll = 'payroll';
    case Finance = 'finance';
    case Customers = 'customers';
    case Inventory = 'inventory';
    case Procurement = 'procurement';
    case Announcements = 'announcements';
    case DecisionSupport = 'decision_support';
}
