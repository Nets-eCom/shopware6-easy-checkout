# Create custom flow with Nexi Nets actions and flow builder

## Overview

Nexi Nets Payment Plugin allows administrators to automate various processes within the payment system with help of flow builder. 

[Read more about Flow builder](https://developer.shopware.com/docs/guides/plugins/plugins/framework/flow/add-flow-builder-action.html).

## Key Features

- **Visual Interface**: Drag-and-drop interface to create workflows.
- **Triggers**: Define events that start the workflow (e.g., payment status changes).
- **Actions**: Specify actions to be taken when a trigger occurs (e.g., make a full charge, send a notification).
- **Conditions**: Add conditions to control the flow of actions based on specific criteria.

## Accessing Flow Builder

1. Log in to the admin panel.
2. Navigate to `Settings` > `Shop` > `Flow Builder`.

## Add Nexi Nets action into flow

1. Click on event that you want to override.
2. Navigate to `Flow` tab.
2. Add `Action (THEN)` or click on `Select action` dropdown
2. Navigate to `Order` section.
3. Choose Nexi Nets action that you want to add into flow.
6. Save the flow.

## List of Nexi Nets actions

### Make Full Charge

- Send charge request to Nexi/Nets API

### Make Cancel? 

- Send cancel payment request to Nexi/Nets API

## Managing Flows

### Auto capture for digital goods

- Create a new flow or edit an existing one
- Add a trigger: Choose an event that will start the workflow, such as `Order placed`
- Add a condition: `Click on Condition (IF)`
- Select Order contains digital goods


## Troubleshooting

- Maybe we are going to have some shooting idk
