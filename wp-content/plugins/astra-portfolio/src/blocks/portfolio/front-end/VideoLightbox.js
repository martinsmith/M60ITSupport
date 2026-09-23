import { useEffect } from "react";
import "./fstyle.css";

// Function to convert YouTube URL to embed format
const getEmbedUrl = (url) => {
  // Check for YouTube URLs
  const youtubeMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
  if (youtubeMatch) {
    return `https://www.youtube.com/embed/${youtubeMatch[1]}?autoplay=1`;
  }

  // Check for Vimeo URLs
  const vimeoMatch = url.match(/vimeo\.com\/(\d+)/);
  if (vimeoMatch) {
    return `https://player.vimeo.com/video/${vimeoMatch[1]}?autoplay=1`;
  }

  // Return the original URL if it's not recognized
  return url;

};

const VideoLightbox = ({ videoUrl, onClose }) => {
  useEffect(() => {
    const handleEscKey = (event) => {
      if (event.key === "Escape") onClose();
    };
    window.addEventListener("keydown", handleEscKey);
    return () => window.removeEventListener("keydown", handleEscKey);
  }, [onClose]);

  // Get embed URL for YouTube videos
  const embedUrl = getEmbedUrl(videoUrl);

  return (
    <div className="video-lightbox-overlay" onClick={onClose}>
      <div className="video-lightbox-content" onClick={(e) => e.stopPropagation()}>
        <button className="close-button" onClick={onClose}>
          &times;
        </button>
        <iframe
          src={embedUrl}
          frameBorder="0"
          allow="autoplay; encrypted-media"
          allowFullScreen
          title="Video Preview"
          className="video-iframe"
        />
      </div>
    </div>
  );
};

export default VideoLightbox;
