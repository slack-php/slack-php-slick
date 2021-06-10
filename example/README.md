# Slick Example App

These instructions will help you set up the app to run locally and configure Slack to point your locally running app.

## Requirements

- Docker
- [Expose][1] or [Ngrok][2]
- A Slack workspace in which you can create and configure apps.

## Steps

1. Configure the app in Slack:
   1. Go to https://api.slack.com/apps, where you can manage your Slack apps.
   2. Create a new app by importing the included `app-manifest.yml` (see [Slack's "Using Manifests" docs][3]).
   3. Go to "Basic Information" in your app's "Settings" sidebar menu and copy the "Signing Secret" value.
   4. Go to "Install App" in your app's "Settings" sidebar menu and install the app to the workspace.
2. Run the app locally:
   1. Clone the code from GitHub.
   2. `cd` to the `example` directory within your local clone of the repo.
   3. Run `SLACK_SIGNING_KEY=CHANGE_ME docker compose up`, but replace `CHANGE_ME` with the signing secret you copied
      in **Step 1.2**.
   4. Use `expose` (or `ngrok`) do expose a tunnel to your locally running app on port 8080. For `expose`:
      1. Run `expose share http://localhost:8080`.
      2. Copy the "Expose-URL" value (should look something like `https://9ldxhcus48.sharedwithexpose.com`).
3. Configure the correct URL in Slack:
   1. Go back to your app's configuration in https://api.slack.com/apps.
   2. Go to "Slash Commands" in your app's "Features" sidebar menu.
   3. Click the pencil button for the `/cool` command to edit it.
   4. Update the "Request URL" with the "Expose-URL" value you copied in **Step 2.4.2**.
   5. Click the "Save" button on the bottom right.
4. Test your slash command:
   1. Go to workspace in Slack.
   2. Type `/cool` and hit enter.
   3. You should see a response from "Cool App" that says:
      > Thanks for running the /cool command. You are cool! :+1:

[1]: https://expose.beyondco.de/
[2]: https://ngrok.com/
[3]: https://api.slack.com/reference/manifests#using
