# Postprocessors

Postprocessors are plugins that perform certain extra tasks once the correct
contact, contribution, or other entity has been identified.

## Previewing postprocessors

Active post processors are being checked for whether they are likely to be
executed (skipping contribution-related checks, since there aren't any
contributions at preview time). The contribution-related checks are being
skipped (since there aren't any contributions at preview time). A preview for
each to-be-executed postprocessor is shown in each suggestion inside a collapsed
accordion element in the top-right corner of each suggestion. When collapsed,
only the number of to-be-executed post processors is shown (e.g. "2 Post
processors"). Expanding the accordion will place it below the suggestion markup
and display whatever markup post processors are providing, or only their name,
if they don't have a specific implementation for previewing.
