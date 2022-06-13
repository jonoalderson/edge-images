document.body.addEventListener(
  "load",
  (e) => {
    // Bail if this isn't an image.
    if (e.target.tagName != "IMG") {
      return;
    }
    // Bail if this isn't an Edge image.
    if (e.classList.contains('edge-images-img') == false) {
      return;
    }
    e.target.style.backgroundImage = "none";
  },
  true
);
