Refactor the avatar component on the dashboard to enhance usability and visuals:

Requirements:

Bigger avatar frame: Increase the avatar size to 160px (width and height) and ensure the container respects this size without clipping.
Hover overlay:
On hover, the avatar dims slightly (brightness filter).
A semi-transparent overlay with a camera icon and the text “Modifier la photo” appears.
Ensure the text is fully visible and does not wrap or get clipped.
Use smooth fade-in/fade-out transitions (CSS opacity + transition).
Direct upload:
Clicking anywhere on the avatar should open the local file picker.
Accept only image files, max 5MB.
Show a loading indicator while uploading.
Automatically reload or update the avatar after successful upload.
CSS checks:
Ensure parent containers or flex/grid layouts do not clip or overlap the hover overlay.
Use overflow: visible or proper z-index if needed.
Make the hover text and icon centrally aligned.
Smooth transitions:
All hover and upload states should feel smooth and responsive.
Cross-browser and responsive:
Ensure avatar scales well on different screen sizes without breaking layout.

Provide the updated HTML, CSS, and JS to implement this behavior.
