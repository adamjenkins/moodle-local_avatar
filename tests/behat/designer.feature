@local @local_avatar
Feature: Users design their own VR avatar
  In order to look like myself in the live rooms of VR room activities
  As a user
  I need to choose the parts of my avatar on the My VR avatar page and save them

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |

  @javascript
  Scenario: Open My VR avatar from the profile, choose parts, save and see them after a reload
    Given I log in as "student1"
    And I follow "Profile" in the user menu
    When I click on "My VR avatar" "link"
    Then I should see "The same avatar is used in every live room on this site"
    And I should see "Only the people in the same live room as you see your avatar"
    And I should see "You have not saved an avatar yet"
    And the field "Capsule" matches value "1"
    And I set the field "Slim" to "1"
    And I set the field "Rounded square" to "1"
    And I set the field "Teal" in the "Body colour" "fieldset" to "1"
    And I set the field "Yellow" in the "Accent colour" "fieldset" to "1"
    And I set the field "Bun" to "1"
    And I set the field "Auburn" to "1"
    And I set the field "Beanie" to "1"
    And I set the field "Glasses" to "1"
    And I set the field "Show my profile picture on my avatar" to "1"
    And I press "Save"
    And I should see "Your avatar was saved." in the "[data-region='status']" "css_element"
    And I should see "Stop using my avatar (standard look)"
    And I reload the page
    And I should not see "You have not saved an avatar yet"
    And the field "Slim" matches value "1"
    And the field "Capsule" matches value ""
    And the field "Rounded square" matches value "1"
    And the field "Teal" in the "Body colour" "fieldset" matches value "1"
    And the field "Blue" in the "Body colour" "fieldset" matches value ""
    And the field "Yellow" in the "Accent colour" "fieldset" matches value "1"
    And the field "Bun" matches value "1"
    And the field "Auburn" matches value "1"
    And the field "Beanie" matches value "1"
    And the field "Glasses" matches value "1"
    And the field "Show my profile picture on my avatar" matches value "1"

  @javascript
  Scenario: Reset to default puts the default choices back in the form until saved
    Given I log in as "student1"
    And I visit "/local/avatar/index.php"
    And I set the field "Broad" to "1"
    And I set the field "Red" in the "Body colour" "fieldset" to "1"
    And I set the field "Glasses" to "1"
    And I press "Save"
    And I should see "Your avatar was saved." in the "[data-region='status']" "css_element"
    When I press "Reset to default"
    Then I should see "The default choices are back in the form" in the "[data-region='status']" "css_element"
    And the field "Capsule" matches value "1"
    And the field "Blue" in the "Body colour" "fieldset" matches value "1"
    And the field "Glasses" matches value ""
    And I reload the page
    And the field "Broad" matches value "1"
    And the field "Red" in the "Body colour" "fieldset" matches value "1"

  Scenario: Without JavaScript the choices are saved and the avatar can be removed again
    Given I log in as "student1"
    And I visit "/local/avatar/index.php"
    When I set the field "Long" to "1"
    And I set the field "Sun hat" to "1"
    And I press "Save"
    Then I should see "Your avatar was saved."
    And the field "Long" matches value "1"
    And the field "Sun hat" matches value "1"
    And I press "Stop using my avatar (standard look)"
    And I should see "Your avatar was removed. You have the standard look again."
    And I should see "You have not saved an avatar yet"
    And the field "No hair" matches value "1"
    And the field "No hat" matches value "1"

  Scenario: The site can turn off profile pictures on avatars
    Given the following config values are set as admin:
      | config       | value | plugin       |
      | allowpicture | 0     | local_avatar |
    And I log in as "student1"
    When I visit "/local/avatar/index.php"
    Then I should see "The site does not show profile pictures on avatars."
    And I should not see "Show my profile picture on my avatar"

  Scenario: An administrator finds the profile picture setting on by default
    Given I log in as "admin"
    When I navigate to "Plugins > Local plugins > VR avatar" in site administration
    Then the field "Profile picture on avatars" matches value "1"

  @javascript @local_avatar_webgl
  Scenario: The 3D preview is shown where the browser has WebGL
    Given I log in as "student1"
    When I visit "/local/avatar/index.php"
    Then "[data-region='canvas']" "css_element" should be visible
    And I should not see "This browser cannot show the 3D preview"
    And I should see "Drag the preview, or use the left and right arrow keys, to turn it."
