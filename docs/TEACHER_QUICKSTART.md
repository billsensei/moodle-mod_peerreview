# Peer review: teacher quick start

Students give each other marks and comments on something that happened in class, such as a talk, a role-play or group work. They use their phones. You choose who reviews whom, you watch the reviews come in, and you decide when students can see them.

## 1. Create the activity

1. Turn editing on in your course and choose **Add an activity or resource → Peer review**.
2. Give it a name, for example "Presentations week 5".
3. Under **Peer review**, check these settings:
   - **Reviewer identity shown to reviewees**: leave it on **Anonymous** if students should not know who reviewed them. You can always see the names yourself.
   - **Aggregation of received reviews**: **Mean** (average) or **Median** (middle mark, less affected by one very high or very low mark).
   - **Open date / Close date** (optional): students can only review between these times. Without dates, it is always open.
4. Under **Grade**:
   - **Maximum grade**: the grade a student gets from their peers, 100 by default.
     Instead of points you can choose a **scale** (for example Poor / Fair / Good / Excellent). Reviewers then pick one item; the peers' marks are averaged and rounded to the nearest item. You cannot switch between points and a scale once reviews exist. The participation grade is always points.
   - **Grading method**: choose **Rubric** or **Marking guide** if you want students to use one. Leave **Simple direct grading** for a plain score and a comment.
   - **Participation grade** (optional): a second grade for *doing* the reviews. A student who completes all their reviews gets full marks.
5. Save.

## 2. Set up the rubric (only if you chose Rubric or Marking guide)

1. Open the activity. In its menu choose **Advanced grading**, then **Define new grading form from scratch**.
2. Add your criteria (for example "Content", "Delivery") and the levels with their points.
3. Press **Save rubric and make it ready** (or **Save marking guide and make it ready**).

Tip: a rubric you used before can be reused. Choose **Create new grading form from a template** instead.

## 3. Decide who reviews whom

Open the activity and press **Allocate reviewers**. Choose a tab:

- **Random**: each student reviews a set number of classmates. The reviews are shared out evenly and nobody reviews themselves. This is the usual choice.
- **Within group**: everyone reviews everyone else in their group. This is good for group work.
- **Rotation**: students review the student a few places further down a list. This is useful when students present in a set order.
- **Manual**: pick the pairs yourself.
- **CSV**: upload a spreadsheet of pairs.

For Random, Within group and Rotation, press **Preview** first. Nothing is saved until you press **Confirm and save**. **Current allocations** lists all pairs, and you can remove pairs there.

Students who join the course later can be added with another **Random** allocation. With **Replace allocations that have not been started** left unticked, it only adds the missing reviews.

## 4. Run it in class

If you want students to compare their own view with their classmates', turn on **Allow self-review** in the activity settings, then open the **Self-assessment** tab on the allocation page and press **Preview** and **Confirm and save**. Every student gets a review of themselves. Self-reviews never count in the received grade. When you release the feedback, a student who has submitted their self-review and has at least one peer review sees a box with their self-assessment next to their peers' grade, and for a rubric or marking guide, the scores per criterion.

Tell students to open the activity on their phones (see the [student how-to](STUDENT_HOWTO.md)). Each student sees a card for every classmate they must review.

To follow progress, open the activity and press **Report**. The table shows how many reviews each student has given and received, and it refreshes itself every 15 seconds. Click a student's name to read their reviews. With a rubric or marking guide, the table **Average by criterion** below it shows the class average on each criterion (for example Delivery), so you can see where the class was strong or weak. It counts submitted reviews of the students in the group you are viewing.

To nudge students who are behind, press **Remind students with reviews to do** on the same page. Every student in the current group view who has reviews left gets a message (popup, email or the Moodle app, depending on their notification settings). It only works while the activity is open.

To have Moodle do it for you, set a close date and tick **Remind students before the close date** in the activity settings and choose how long before it (any time from 1 hour to 52 weeks, for example 2 days or 36 hours). Moodle checks once an hour, so the message can arrive up to an hour after that moment. It sends one message to every student who still has reviews left, from the site's no-reply address. It is sent once for each close date; if you change the close date or the lead time it is armed again. It is off by default, and it needs the Moodle cron to be running (ask your administrator).

## 5. Release the feedback

Students do not see the reviews they received until you allow it. On the **Report** page, press **Release feedback**. You can press **Hide feedback** later to hide it again.

If the activity is anonymous, students see "Anonymous" instead of the reviewer's name.

## 6. Check and send the grades

- On the **Report** page, the **Received grade** column shows each student's grade from their peers. Only submitted reviews count, and self-reviews never count.
- To change a student's grade, click their name, enter a new grade and a note, and save. **Remove override** goes back to the peers' grade.
- Grades go to the gradebook only when you press **Push grades to gradebook**. Press it again after any change you want students to see in the gradebook.

## Good to know

- Students can **save a draft** and finish later, and can change a submitted review until the closing time.
- If you change the rubric after students have started, their stored grades stay as they are until they submit again.
- **Export all reviews** on the report page downloads every review, for example as a spreadsheet.
- To reuse the activity with another class, use **Course reuse → Reset** and tick "Delete allocations, reviews and grade overrides".
