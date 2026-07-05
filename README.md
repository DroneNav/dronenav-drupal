# DroneNav Drupal Governance

## Overview

The DroneNav Drupal application provides the governance and administrative interface for the DroneNav platform.

Rather than serving as the operational flight management system, Drupal provides the workflows, security, configuration, and administrative capabilities required to govern a large-scale drone navigation network.

The DroneNav architecture intentionally separates operational concerns from governance concerns. This allows each technology stack to focus on what it does best while maintaining clear system boundaries.

---

## Architecture

The DroneNav platform consists of three major components.

```
+--------------------------------------------------------------+
|                     DroneNav Platform                        |
+--------------------------------------------------------------+

         React Application
    Operational User Interface
             │
             ▼
        Flask REST API
 Operational System of Record
             │
             ▼
        PostgreSQL / PostGIS


             ▲
             │
      Drupal Governance
   Administration & Workflow
```

Each component has a single responsibility.

### React

The React application provides the operational user experience including interactive mapping, overlay creation, flight planning, and operational visualization.

### Flask API

The Flask API is the operational system of record.

It owns:

- Spatial data
- Overlay management
- Flight planning
- Operational state
- Validation
- Business rules
- Geospatial processing

The API is considered the authoritative source for all operational information.

### Drupal

Drupal provides the governance layer.

Its responsibilities include:

- User management
- Authentication
- Authorization
- Administrative workflows
- Survey management
- Review processes
- Configuration management
- Content management
- Auditability
- Reporting
- Operational administration

Drupal does **not** own operational state.

Instead, it provides the workflows that govern operational state managed by the API.

---

# Separation of Concerns

A primary design objective of DroneNav is maintaining a clear separation between governance and operations.

```
Governance                    Operations
-----------                  ----------------

Drupal      ----------->     Flask API

Workflow                     Validation
Approvals                    Spatial Data
Administration               Flight Planning
Users                         Operational Status
Reviews                      Geospatial Logic
```

This separation provides several important benefits:

- Clear system boundaries
- Reduced architectural complexity
- Independent deployment
- Independent scaling
- Improved maintainability
- Simplified testing
- Reduced coupling
- Long-term flexibility

---

# Why Drupal?

Drupal was selected because it provides an enterprise-grade governance platform that would otherwise require years of custom development.

Rather than rebuilding common enterprise capabilities, DroneNav leverages Drupal's mature ecosystem while focusing development effort on aviation-specific functionality.

Drupal provides:

- Mature security model
- Enterprise authentication
- Role-based authorization
- Fine-grained permissions
- Configurable content modeling
- Administrative forms
- Field management
- Content revisions
- Administrative workflows
- Views and reporting
- Menu management
- Routing
- Internationalization
- Extensible plugin architecture
- REST integration
- Long-term maintainability

These capabilities allow development effort to remain focused on DroneNav-specific business logic instead of recreating common administrative infrastructure.

---

# Governance Philosophy

DroneNav treats governance as a first-class capability rather than an afterthought.

Examples include:

- Survey workflows
- Overlay reviews
- Site package reviews
- Operational activation
- Operational deactivation
- Administrative configuration

Operational changes are intentional, validated, and auditable.

---

# API Integration

Drupal communicates with the operational platform exclusively through REST APIs.

Examples include:

- Synchronizing reference data
- Synchronizing operational overlays
- Survey submission
- Review approval
- Overlay activation
- Overlay deactivation
- Site package activation
- Site package deactivation

Operational data remains owned by the API.

Drupal maintains only the information necessary to provide governance workflows.

---

# Synchronization Strategy

DroneNav intentionally distinguishes between two categories of synchronized information.

## Reference Data

Reference data changes infrequently.

Examples:

- Authorities
- Lookup values
- Configuration data

These records are synchronized from the API into Drupal for administrative purposes.

---

## Operational Data

Operational data represents the current state of the navigation network.

Examples:

- Sites
- Zones
- DronePorts
- Routes

These records are synchronized from the API into Drupal to support governance workflows.

The API remains the system of record.

---

# Design Principles

Several principles guide development of the Drupal application.

## API First

The operational API is the authoritative source of operational information.

Drupal never becomes the operational system of record.

---

## Governance Before Operations

Operational changes occur only through defined governance workflows where appropriate.

---

## Single Responsibility

Each component of the DroneNav platform has one clearly defined responsibility.

---

## Configuration Over Code

Whenever practical, Drupal capabilities are implemented using configurable entities, fields, permissions, and administrative interfaces rather than custom code.

---

## Security by Design

Role-based access control is enforced throughout the governance interface.

Permissions are granted using the principle of least privilege.

---

## Maintainability

The Drupal implementation favors modular design with clear separation between:

- Controllers
- Services
- Forms
- Routing
- Administrative interfaces

Business logic resides within service classes whenever possible.

---

# Repository Organization

```
src/
    Controller/
    Form/
    Service/
    Commands/

config/
templates/
```

The project follows Drupal development conventions while maintaining a service-oriented architecture.

---

# Future Direction

As DroneNav evolves, the Drupal application will continue to provide the administrative and governance capabilities for the platform while operational capabilities expand within the API.

Future administrative capabilities include:

- Flight Band administration
- Authority administration
- Operational configuration
- System monitoring
- Reporting
- Network administration

The underlying architectural principle remains unchanged:

**Drupal governs the platform.**

**The API operates the platform.**
