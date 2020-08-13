# Entity Browser - Table Layout

## INTRODUCTION

The Entity Browser- Table module provides a new Field Widget for
Entity Reference fields. The new widget will display referenced
content as a table along with the bundle type  (content type
for Nodes or bundle for Media). It is most useful when referencing
content where you only want to display the label of the entity.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/entity_browser_table

 * To submit bug reports and feature suggestions, or to track changes:
   https://www.drupal.org/project/issues/entity_browser_table

## REQUIREMENTS

This module requires the following modules:

 * Entity Browser - Version 2.2 or above (https://www.drupal.org/project/entity_browser)
 

## INSTALLATION

 * Install as you would normally install a contributed Drupal module. Visit:
   https://www.drupal.org/documentation/install/modules-themes/modules-8
   for further information.
   
If you install via composer all dependencies will be handled automatically. If not, you will need to manually download 
and install the Entity Browser module.

## CONFIGURATION

 * Install the module your usual Drupal way.

 * Edit a content Type that contains an Entity Reference field.

 * From the Manage Form Display tab change the Widget for the field
   to Entity Browser - Table.

 * From the Settings for for the field (the cog/gear icon) ensure
   the Entity Display option is set to Entity label.

 * Update the field and Save the content type changes.
