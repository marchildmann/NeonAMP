<?php
declare(strict_types=1);

/**
 * NeonAMP - Web Audio API MP3 Player
 * PHP 8.3+ Required
 */

// Configuration
define('MUSIC_PATH', __DIR__ . '/music');
define('DB_PATH', __DIR__ . '/library.sqlite');

// ID3v1 Genre list
const ID3_GENRES = [
    0 => 'Blues', 1 => 'Classic Rock', 2 => 'Country', 3 => 'Dance', 4 => 'Disco',
    5 => 'Funk', 6 => 'Grunge', 7 => 'Hip-Hop', 8 => 'Jazz', 9 => 'Metal',
    10 => 'New Age', 11 => 'Oldies', 12 => 'Other', 13 => 'Pop', 14 => 'R&B',
    15 => 'Rap', 16 => 'Reggae', 17 => 'Rock', 18 => 'Techno', 19 => 'Industrial',
    20 => 'Alternative', 21 => 'Ska', 22 => 'Death Metal', 23 => 'Pranks', 24 => 'Soundtrack',
    25 => 'Euro-Techno', 26 => 'Ambient', 27 => 'Trip-Hop', 28 => 'Vocal', 29 => 'Jazz+Funk',
    30 => 'Fusion', 31 => 'Trance', 32 => 'Classical', 33 => 'Instrumental', 34 => 'Acid',
    35 => 'House', 36 => 'Game', 37 => 'Sound Clip', 38 => 'Gospel', 39 => 'Noise',
    40 => 'Alternative Rock', 41 => 'Bass', 42 => 'Soul', 43 => 'Punk', 44 => 'Space',
    45 => 'Meditative', 46 => 'Instrumental Pop', 47 => 'Instrumental Rock', 48 => 'Ethnic',
    49 => 'Gothic', 50 => 'Darkwave', 51 => 'Techno-Industrial', 52 => 'Electronic',
    53 => 'Pop-Folk', 54 => 'Eurodance', 55 => 'Dream', 56 => 'Southern Rock', 57 => 'Comedy',
    58 => 'Cult', 59 => 'Gangsta', 60 => 'Top 40', 61 => 'Christian Rap', 62 => 'Pop/Funk',
    63 => 'Jungle', 64 => 'Native US', 65 => 'Cabaret', 66 => 'New Wave', 67 => 'Psychedelic',
    68 => 'Rave', 69 => 'Showtunes', 70 => 'Trailer', 71 => 'Lo-Fi', 72 => 'Tribal',
    73 => 'Acid Punk', 74 => 'Acid Jazz', 75 => 'Polka', 76 => 'Retro', 77 => 'Musical',
    78 => 'Rock & Roll', 79 => 'Hard Rock', 80 => 'Folk', 81 => 'Folk-Rock', 82 => 'National Folk',
    83 => 'Swing', 84 => 'Fast Fusion', 85 => 'Bebop', 86 => 'Latin', 87 => 'Revival',
    88 => 'Celtic', 89 => 'Bluegrass', 90 => 'Avantgarde', 91 => 'Gothic Rock', 92 => 'Progressive Rock',
    93 => 'Psychedelic Rock', 94 => 'Symphonic Rock', 95 => 'Slow Rock', 96 => 'Big Band',
    97 => 'Chorus', 98 => 'Easy Listening', 99 => 'Acoustic', 100 => 'Humour', 101 => 'Speech',
    102 => 'Chanson', 103 => 'Opera', 104 => 'Chamber Music', 105 => 'Sonata', 106 => 'Symphony',
    107 => 'Booty Bass', 108 => 'Primus', 109 => 'Porn Groove', 110 => 'Satire', 111 => 'Slow Jam',
    112 => 'Club', 113 => 'Tango', 114 => 'Samba', 115 => 'Folklore', 116 => 'Ballad',
    117 => 'Power Ballad', 118 => 'Rhythmic Soul', 119 => 'Freestyle', 120 => 'Duet',
    121 => 'Punk Rock', 122 => 'Drum Solo', 123 => 'A Cappella', 124 => 'Euro-House', 125 => 'Dance Hall'
];

/**
 * Parse ID3 tags from MP3 file
 * Supports ID3v1, ID3v1.1, ID3v2.3, ID3v2.4
 */
function parseId3Tags(string $filepath): array
{
    $tags = [
        'title' => null,
        'artist' => null,
        'album' => null,
        'year' => null,
        'genre' => null,
        'track_number' => null,
        'duration' => null,
        'artwork' => null,
    ];

    if (!file_exists($filepath) || !is_readable($filepath)) {
        return $tags;
    }

    $handle = fopen($filepath, 'rb');
    if (!$handle) {
        return $tags;
    }

    // Try ID3v2 first (at the beginning of file)
    $header = fread($handle, 10);
    if (strlen($header) >= 10 && substr($header, 0, 3) === 'ID3') {
        $tags = parseId3v2($handle, $header, $tags);
    }

    // Try ID3v1 at end of file (last 128 bytes)
    fseek($handle, -128, SEEK_END);
    $id3v1 = fread($handle, 128);
    if (strlen($id3v1) === 128 && substr($id3v1, 0, 3) === 'TAG') {
        $tags = parseId3v1($id3v1, $tags);
    }

    // Estimate duration from file size and bitrate
    if ($tags['duration'] === null) {
        $tags['duration'] = estimateDuration($handle, $filepath);
    }

    fclose($handle);

    return $tags;
}

/**
 * Parse ID3v1 tags
 */
function parseId3v1(string $data, array $tags): array
{
    $title = trim(substr($data, 3, 30));
    $artist = trim(substr($data, 33, 30));
    $album = trim(substr($data, 63, 30));
    $year = trim(substr($data, 93, 4));
    $comment = substr($data, 97, 30);
    $genreIndex = ord($data[127]);

    // ID3v1.1: track number in comment field
    if (ord($comment[28]) === 0 && ord($comment[29]) !== 0) {
        $trackNumber = ord($comment[29]);
        if ($tags['track_number'] === null) {
            $tags['track_number'] = $trackNumber;
        }
    }

    // Only use ID3v1 values if ID3v2 didn't provide them
    if ($tags['title'] === null && $title !== '') {
        $tags['title'] = cleanString($title);
    }
    if ($tags['artist'] === null && $artist !== '') {
        $tags['artist'] = cleanString($artist);
    }
    if ($tags['album'] === null && $album !== '') {
        $tags['album'] = cleanString($album);
    }
    if ($tags['year'] === null && $year !== '' && is_numeric($year)) {
        $tags['year'] = (int) $year;
    }
    if ($tags['genre'] === null && isset(ID3_GENRES[$genreIndex])) {
        $tags['genre'] = ID3_GENRES[$genreIndex];
    }

    return $tags;
}

/**
 * Parse ID3v2 tags
 */
function parseId3v2($handle, string $header, array $tags): array
{
    $version = ord($header[3]);
    $flags = ord($header[5]);
    $size = unsyncsafe(substr($header, 6, 4));

    // Read entire tag
    $tagData = fread($handle, $size);
    if (strlen($tagData) < $size) {
        return $tags;
    }

    $pos = 0;
    while ($pos < $size - 10) {
        // Frame header: 4 bytes ID, 4 bytes size, 2 bytes flags
        $frameId = substr($tagData, $pos, 4);
        if ($frameId === "\0\0\0\0" || strlen($frameId) < 4) {
            break;
        }

        if ($version >= 4) {
            $frameSize = unsyncsafe(substr($tagData, $pos + 4, 4));
        } else {
            $frameSize = unpack('N', substr($tagData, $pos + 4, 4))[1];
        }

        if ($frameSize <= 0 || $pos + 10 + $frameSize > $size) {
            break;
        }

        $frameData = substr($tagData, $pos + 10, $frameSize);

        // Parse text frames
        $value = parseId3v2Frame($frameId, $frameData);
        if ($value !== null) {
            match ($frameId) {
                'TIT2' => $tags['title'] = $value,
                'TPE1' => $tags['artist'] = $value,
                'TALB' => $tags['album'] = $value,
                'TYER', 'TDRC' => $tags['year'] = (int) substr($value, 0, 4),
                'TRCK' => $tags['track_number'] = (int) explode('/', $value)[0],
                'TCON' => $tags['genre'] = parseGenre($value),
                'TLEN' => $tags['duration'] = (int) ($value / 1000),
                default => null
            };
        }

        // Parse album art (APIC frame)
        if ($frameId === 'APIC' && $tags['artwork'] === null) {
            $tags['artwork'] = parseApicFrame($frameData);
        }

        $pos += 10 + $frameSize;
    }

    return $tags;
}

/**
 * Parse APIC (album art) frame from ID3v2
 */
function parseApicFrame(string $data): ?string
{
    if (strlen($data) < 4) {
        return null;
    }

    $encoding = ord($data[0]);
    $pos = 1;

    // Read MIME type (null-terminated)
    $mimeEnd = strpos($data, "\0", $pos);
    if ($mimeEnd === false) {
        return null;
    }
    $mimeType = substr($data, $pos, $mimeEnd - $pos);
    $pos = $mimeEnd + 1;

    // Skip picture type byte
    $pos++;

    // Skip description (null-terminated, encoding-dependent)
    if ($encoding === 1 || $encoding === 2) {
        // UTF-16: look for double null
        while ($pos < strlen($data) - 1) {
            if ($data[$pos] === "\0" && $data[$pos + 1] === "\0") {
                $pos += 2;
                break;
            }
            $pos++;
        }
    } else {
        // ISO-8859-1 or UTF-8: single null
        $descEnd = strpos($data, "\0", $pos);
        if ($descEnd !== false) {
            $pos = $descEnd + 1;
        }
    }

    // Rest is image data
    $imageData = substr($data, $pos);
    if (strlen($imageData) < 100) {
        return null;
    }

    // Return as base64 data URI
    if (empty($mimeType) || $mimeType === 'image/') {
        // Detect from data
        if (substr($imageData, 0, 3) === "\xFF\xD8\xFF") {
            $mimeType = 'image/jpeg';
        } elseif (substr($imageData, 0, 8) === "\x89PNG\r\n\x1a\n") {
            $mimeType = 'image/png';
        } else {
            $mimeType = 'image/jpeg';
        }
    }

    return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
}

/**
 * Parse ID3v2 text frame
 */
function parseId3v2Frame(string $frameId, string $data): ?string
{
    if (strlen($data) < 2) {
        return null;
    }

    // Text frames start with T
    if ($frameId[0] !== 'T') {
        return null;
    }

    $encoding = ord($data[0]);
    $text = substr($data, 1);

    // Handle different encodings
    $text = match ($encoding) {
        0 => $text, // ISO-8859-1
        1 => mb_convert_encoding($text, 'UTF-8', 'UTF-16'), // UTF-16 with BOM
        2 => mb_convert_encoding($text, 'UTF-8', 'UTF-16BE'), // UTF-16BE
        3 => $text, // UTF-8
        default => $text
    };

    return cleanString($text);
}

/**
 * Parse genre string (handles ID3v1 index references like "(17)" or "(17)Rock")
 */
function parseGenre(string $genre): string
{
    if (preg_match('/^\((\d+)\)(.*)$/', $genre, $matches)) {
        $index = (int) $matches[1];
        $extra = trim($matches[2]);
        if ($extra !== '') {
            return $extra;
        }
        return ID3_GENRES[$index] ?? 'Unknown';
    }
    return $genre;
}

/**
 * Convert syncsafe integer
 */
function unsyncsafe(string $bytes): int
{
    $result = 0;
    for ($i = 0; $i < 4; $i++) {
        $result = ($result << 7) | (ord($bytes[$i]) & 0x7F);
    }
    return $result;
}

/**
 * Clean string from null bytes and trim
 */
function cleanString(string $str): string
{
    $str = str_replace("\0", '', $str);
    $str = trim($str);
    // Convert to UTF-8 if needed
    if (!mb_check_encoding($str, 'UTF-8')) {
        $str = mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
    }
    return $str;
}

/**
 * Estimate duration from MP3 file
 */
function estimateDuration($handle, string $filepath): ?int
{
    $filesize = filesize($filepath);
    fseek($handle, 0);

    // Find first MP3 frame to get bitrate
    $data = fread($handle, 16384);
    $pos = 0;
    $bitrate = 128; // Default assumption

    while ($pos < strlen($data) - 4) {
        // Look for frame sync (11 bits set)
        if (ord($data[$pos]) === 0xFF && (ord($data[$pos + 1]) & 0xE0) === 0xE0) {
            $header = unpack('N', substr($data, $pos, 4))[1];
            $bitrateIndex = ($header >> 12) & 0x0F;
            $version = ($header >> 19) & 0x03;
            $layer = ($header >> 17) & 0x03;

            // MPEG1 Layer 3 bitrate table
            $bitrates = [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 0];
            if ($bitrateIndex > 0 && $bitrateIndex < 15) {
                $bitrate = $bitrates[$bitrateIndex];
            }
            break;
        }
        $pos++;
    }

    if ($bitrate > 0) {
        return (int) (($filesize * 8) / ($bitrate * 1000));
    }

    return null;
}

// Initialize database
function initDatabase(): PDO
{
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tracks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            path TEXT UNIQUE NOT NULL,
            filename TEXT NOT NULL,
            title TEXT,
            artist TEXT,
            album TEXT,
            year INTEGER,
            genre TEXT,
            track_number INTEGER,
            duration INTEGER DEFAULT 0,
            filesize INTEGER DEFAULT 0,
            added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            play_count INTEGER DEFAULT 0,
            last_played_at DATETIME
        );

        CREATE TABLE IF NOT EXISTS playlists (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS playlist_tracks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            playlist_id INTEGER NOT NULL,
            track_id INTEGER NOT NULL,
            position INTEGER NOT NULL,
            FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
            FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE
        );

        CREATE INDEX IF NOT EXISTS idx_tracks_artist ON tracks(artist);
        CREATE INDEX IF NOT EXISTS idx_tracks_album ON tracks(album);
        CREATE INDEX IF NOT EXISTS idx_tracks_genre ON tracks(genre);
        CREATE INDEX IF NOT EXISTS idx_tracks_year ON tracks(year);
    ");

    // Migration: Add last_played_at column if it doesn't exist
    $columns = $pdo->query("PRAGMA table_info(tracks)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('last_played_at', $columns)) {
        $pdo->exec("ALTER TABLE tracks ADD COLUMN last_played_at DATETIME");
    }

    return $pdo;
}

// Scan directory for MP3 files
function scanMusicDirectory(PDO $pdo): array
{
    $added = [];
    $removed = 0;

    if (!is_dir(MUSIC_PATH)) {
        mkdir(MUSIC_PATH, 0755, true);
        return ['added' => $added, 'removed' => $removed];
    }

    // First, remove tracks whose files no longer exist
    $existing = $pdo->query("SELECT id, path FROM tracks")->fetchAll(PDO::FETCH_ASSOC);
    $deleteStmt = $pdo->prepare("DELETE FROM tracks WHERE id = :id");

    foreach ($existing as $track) {
        $fullPath = MUSIC_PATH . '/' . $track['path'];
        if (!file_exists($fullPath)) {
            $deleteStmt->execute([':id' => $track['id']]);
            $removed++;
        }
    }

    // Now scan for new files
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(MUSIC_PATH, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    $stmt = $pdo->prepare("
        INSERT OR IGNORE INTO tracks (path, filename, title, artist, album, year, genre, track_number, duration, filesize)
        VALUES (:path, :filename, :title, :artist, :album, :year, :genre, :track_number, :duration, :filesize)
    ");

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'mp3') {
            $relativePath = str_replace(MUSIC_PATH . '/', '', $file->getPathname());
            $filename = $file->getBasename('.mp3');

            // Parse ID3 tags from the MP3 file
            $tags = parseId3Tags($file->getPathname());

            // Use metadata, fall back to filename/folder if not available
            $title = $tags['title'] ?: $filename;
            $artist = $tags['artist'] ?: 'Unknown Artist';
            $album = $tags['album'];
            if (!$album) {
                $album = basename(dirname($file->getPathname()));
                if ($album === 'music') {
                    $album = 'Unknown Album';
                }
            }

            $stmt->execute([
                ':path' => $relativePath,
                ':filename' => $filename,
                ':title' => $title,
                ':artist' => $artist,
                ':album' => $album,
                ':year' => $tags['year'],
                ':genre' => $tags['genre'],
                ':track_number' => $tags['track_number'],
                ':duration' => $tags['duration'] ?? 0,
                ':filesize' => $file->getSize()
            ]);

            if ($pdo->lastInsertId()) {
                $added[] = $relativePath;
            }
        }
    }

    return ['added' => $added, 'removed' => $removed];
}

// Get all tracks with optional filters
function getTracks(PDO $pdo, ?string $search = null, ?string $artist = null, ?string $album = null, ?string $genre = null): array
{
    $sql = "SELECT * FROM tracks";
    $params = [];
    $conditions = [];

    if ($search) {
        $conditions[] = "(title LIKE :search OR artist LIKE :search OR album LIKE :search OR genre LIKE :search OR year LIKE :search)";
        $params[':search'] = "%{$search}%";
    }
    if ($artist) {
        $conditions[] = "artist = :artist";
        $params[':artist'] = $artist;
    }
    if ($album) {
        $conditions[] = "album = :album";
        $params[':album'] = $album;
    }
    if ($genre) {
        $conditions[] = "genre = :genre";
        $params[':genre'] = $genre;
    }

    if ($conditions) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }

    $sql .= " ORDER BY artist, album, track_number, title";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get list of artists with track counts
function getArtists(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT artist, COUNT(*) as track_count, COUNT(DISTINCT album) as album_count
        FROM tracks
        GROUP BY artist
        ORDER BY artist
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get list of albums with track counts
function getAlbums(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT album, artist, year, COUNT(*) as track_count, SUM(duration) as total_duration
        FROM tracks
        GROUP BY album, artist
        ORDER BY artist, year, album
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get list of genres with track counts
function getGenres(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT genre, COUNT(*) as track_count, COUNT(DISTINCT artist) as artist_count
        FROM tracks
        WHERE genre IS NOT NULL AND genre != ''
        GROUP BY genre
        ORDER BY genre
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all playlists
function getPlaylists(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT p.*, COUNT(pt.id) as track_count
        FROM playlists p
        LEFT JOIN playlist_tracks pt ON p.id = pt.playlist_id
        GROUP BY p.id
        ORDER BY p.name
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get playlist tracks
function getPlaylistTracks(PDO $pdo, int $playlistId): array
{
    $stmt = $pdo->prepare("
        SELECT t.* FROM tracks t
        JOIN playlist_tracks pt ON t.id = pt.track_id
        WHERE pt.playlist_id = :playlist_id
        ORDER BY pt.position
    ");
    $stmt->execute([':playlist_id' => $playlistId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Create playlist
function createPlaylist(PDO $pdo, string $name): int
{
    $stmt = $pdo->prepare("INSERT INTO playlists (name) VALUES (:name)");
    $stmt->execute([':name' => $name]);

    return (int) $pdo->lastInsertId();
}

// Add track to playlist
function addToPlaylist(PDO $pdo, int $playlistId, int $trackId): bool
{
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(position), 0) + 1 FROM playlist_tracks WHERE playlist_id = :pid");
    $stmt->execute([':pid' => $playlistId]);
    $position = $stmt->fetchColumn();

    $stmt = $pdo->prepare("INSERT INTO playlist_tracks (playlist_id, track_id, position) VALUES (:pid, :tid, :pos)");

    return $stmt->execute([':pid' => $playlistId, ':tid' => $trackId, ':pos' => $position]);
}

// Remove track from playlist
function removeFromPlaylist(PDO $pdo, int $playlistId, int $trackId): bool
{
    $stmt = $pdo->prepare("DELETE FROM playlist_tracks WHERE playlist_id = :pid AND track_id = :tid");
    return $stmt->execute([':pid' => $playlistId, ':tid' => $trackId]);
}

// Increment play count and update last played timestamp
function incrementPlayCount(PDO $pdo, int $trackId): void
{
    $stmt = $pdo->prepare("UPDATE tracks SET play_count = play_count + 1, last_played_at = datetime('now') WHERE id = :id");
    $stmt->execute([':id' => $trackId]);
}

// Get recently played tracks
function getRecentlyPlayed(PDO $pdo, int $limit = 50): array
{
    $stmt = $pdo->prepare("
        SELECT * FROM tracks
        WHERE last_played_at IS NOT NULL
        ORDER BY last_played_at DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get most played tracks
function getMostPlayed(PDO $pdo, int $limit = 50): array
{
    $stmt = $pdo->prepare("
        SELECT * FROM tracks
        WHERE play_count > 0
        ORDER BY play_count DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Delete playlist
function deletePlaylist(PDO $pdo, int $playlistId): bool
{
    $stmt = $pdo->prepare("DELETE FROM playlists WHERE id = :id");
    return $stmt->execute([':id' => $playlistId]);
}

// Rename playlist
function renamePlaylist(PDO $pdo, int $playlistId, string $name): bool
{
    $stmt = $pdo->prepare("UPDATE playlists SET name = :name WHERE id = :id");
    return $stmt->execute([':name' => $name, ':id' => $playlistId]);
}

// Reorder playlist tracks
function reorderPlaylistTracks(PDO $pdo, int $playlistId, array $trackIds): bool
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE playlist_tracks SET position = :pos WHERE playlist_id = :pid AND track_id = :tid");
        foreach ($trackIds as $position => $trackId) {
            $stmt->execute([':pos' => $position, ':pid' => $playlistId, ':tid' => $trackId]);
        }
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

// Get album artwork for a track
function getTrackArtwork(string $path): ?string
{
    $fullPath = MUSIC_PATH . '/' . $path;
    if (!file_exists($fullPath)) {
        return null;
    }

    $tags = parseId3Tags($fullPath);
    return $tags['artwork'];
}

// Update track duration (called when actual duration is known from playback)
function updateTrackDuration(PDO $pdo, int $trackId, int $duration): void
{
    $stmt = $pdo->prepare("UPDATE tracks SET duration = :duration WHERE id = :id");
    $stmt->execute([':duration' => $duration, ':id' => $trackId]);
}

// Get library stats
function getStats(PDO $pdo): array
{
    $stats = [];

    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(filesize) as size, SUM(play_count) as plays, SUM(duration) as duration FROM tracks");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $stats['total_tracks'] = (int) $row['total'];
    $stats['total_size'] = (int) $row['size'];
    $stats['total_plays'] = (int) $row['plays'];
    $stats['total_duration'] = (int) $row['duration'];

    $stmt = $pdo->query("SELECT COUNT(DISTINCT artist) FROM tracks");
    $stats['total_artists'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(DISTINCT album) FROM tracks");
    $stats['total_albums'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(DISTINCT genre) FROM tracks WHERE genre IS NOT NULL");
    $stats['total_genres'] = (int) $stmt->fetchColumn();

    return $stats;
}

// Format duration to human readable (hours, minutes)
function formatDuration(int $seconds): string
{
    if ($seconds <= 0) return '0m';
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    if ($hours > 0) {
        return "{$hours}h {$minutes}m";
    }
    return "{$minutes}m";
}

// Format bytes to human readable
function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// Handle API requests
$pdo = initDatabase();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $response = match($_POST['action']) {
        'scan' => scanMusicDirectory($pdo),
        'tracks' => ['tracks' => getTracks($pdo, $_POST['search'] ?? null, $_POST['artist'] ?? null, $_POST['album'] ?? null, $_POST['genre'] ?? null)],
        'artists' => ['artists' => getArtists($pdo)],
        'albums' => ['albums' => getAlbums($pdo)],
        'genres' => ['genres' => getGenres($pdo)],
        'recently_played' => ['tracks' => getRecentlyPlayed($pdo)],
        'most_played' => ['tracks' => getMostPlayed($pdo)],
        'playlists' => ['playlists' => getPlaylists($pdo)],
        'playlist_tracks' => ['tracks' => getPlaylistTracks($pdo, (int) $_POST['playlist_id'])],
        'create_playlist' => ['id' => createPlaylist($pdo, $_POST['name'])],
        'delete_playlist' => ['success' => deletePlaylist($pdo, (int) $_POST['playlist_id'])],
        'rename_playlist' => ['success' => renamePlaylist($pdo, (int) $_POST['playlist_id'], $_POST['name'])],
        'reorder_playlist' => ['success' => reorderPlaylistTracks($pdo, (int) $_POST['playlist_id'], json_decode($_POST['track_ids'], true))],
        'add_to_playlist' => ['success' => addToPlaylist($pdo, (int) $_POST['playlist_id'], (int) $_POST['track_id'])],
        'remove_from_playlist' => ['success' => removeFromPlaylist($pdo, (int) $_POST['playlist_id'], (int) $_POST['track_id'])],
        'play_count' => (function() use ($pdo) { incrementPlayCount($pdo, (int) $_POST['track_id']); return ['success' => true]; })(),
        'update_duration' => (function() use ($pdo) { updateTrackDuration($pdo, (int) $_POST['track_id'], (int) $_POST['duration']); return ['success' => true]; })(),
        'artwork' => ['artwork' => getTrackArtwork($_POST['path'])],
        'stats' => ['stats' => getStats($pdo)],
        default => ['error' => 'Unknown action']
    };

    echo json_encode($response);
    exit;
}

// Initial scan
scanMusicDirectory($pdo);
$stats = getStats($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NeonAMP</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-dark: #1a1a2e;
            --bg-medium: #16213e;
            --bg-light: #0f3460;
            --accent: #e94560;
            --text: #eaeaea;
            --text-dim: #a0a0a0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-dark);
            color: var(--text);
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* TOP - Player Controls */
        #top-bar {
            background: var(--bg-medium);
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            border-bottom: 1px solid var(--bg-light);
        }

        .controls {
            display: flex;
            gap: 10px;
        }

        .controls button {
            background: var(--bg-light);
            border: none;
            color: var(--text);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.2s;
        }

        .controls button:hover {
            background: var(--accent);
        }

        .controls button.play-btn {
            width: 50px;
            height: 50px;
            background: var(--accent);
        }

        .volume-control {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .volume-control input {
            width: 80px;
            accent-color: var(--accent);
        }

        .track-info {
            flex: 1;
            min-width: 0;
        }

        .track-info .title {
            font-weight: 600;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .track-info .artist {
            color: var(--text-dim);
            font-size: 12px;
        }

        .progress-container {
            flex: 2;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .progress-bar {
            flex: 1;
            height: 6px;
            background: var(--bg-light);
            border-radius: 3px;
            cursor: pointer;
            position: relative;
        }

        .progress-bar .progress {
            height: 100%;
            background: var(--accent);
            border-radius: 3px;
            width: 0%;
            transition: width 0.1s linear;
        }

        .time {
            font-size: 12px;
            color: var(--text-dim);
            min-width: 40px;
        }

        .search-box {
            display: flex;
            gap: 10px;
        }

        .search-box input {
            background: var(--bg-light);
            border: none;
            padding: 8px 12px;
            border-radius: 20px;
            color: var(--text);
            width: 200px;
        }

        .search-box input::placeholder {
            color: var(--text-dim);
        }

        /* MIDDLE - Main Content */
        #middle {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        /* LEFT - Playlists */
        #sidebar {
            width: 220px;
            background: var(--bg-medium);
            padding: 15px;
            overflow-y: auto;
            border-right: 1px solid var(--bg-light);
        }

        #sidebar h3 {
            font-size: 12px;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 10px;
        }

        .playlist-item, .nav-item {
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 4px;
            font-size: 14px;
            transition: background 0.2s;
        }

        .playlist-item:hover, .nav-item:hover {
            background: var(--bg-light);
        }

        .playlist-item.active, .nav-item.active {
            background: var(--accent);
        }

        .new-playlist {
            margin-top: 10px;
            padding: 8px;
            background: var(--bg-light);
            border: none;
            color: var(--text-dim);
            width: 100%;
            border-radius: 4px;
            cursor: pointer;
        }

        .new-playlist:hover {
            color: var(--text);
        }

        /* MAIN - Track List */
        #main {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        #main h2 {
            margin-bottom: 15px;
            font-size: 24px;
        }

        .track-list {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .track-row {
            display: grid;
            grid-template-columns: 40px 1.5fr 1fr 1fr 60px 60px 40px;
            gap: 15px;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            align-items: center;
            transition: background 0.2s;
        }

        .track-row.draggable {
            grid-template-columns: 30px 40px 1.5fr 1fr 1fr 60px 60px 40px;
        }

        .track-row:hover {
            background: var(--bg-medium);
        }

        .track-row.playing {
            background: var(--bg-light);
        }

        .track-row .num {
            color: var(--text-dim);
            font-size: 14px;
            text-align: center;
        }

        .track-row .track-title {
            font-weight: 500;
        }

        .track-row .track-artist {
            color: var(--text-dim);
        }

        .track-row .track-album {
            color: var(--text-dim);
            font-size: 13px;
        }

        .track-row .track-year {
            color: var(--text-dim);
            font-size: 13px;
            text-align: center;
        }

        .track-row .duration {
            color: var(--text-dim);
            font-size: 13px;
            text-align: right;
        }

        .track-row .menu-btn {
            opacity: 0;
            background: none;
            border: none;
            color: var(--text-dim);
            cursor: pointer;
            padding: 5px;
        }

        .track-row:hover .menu-btn {
            opacity: 1;
        }

        /* Browse Grid (Artists, Albums, Genres) */
        .browse-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
        }

        .browse-item {
            background: var(--bg-medium);
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }

        .browse-item:hover {
            background: var(--bg-light);
            transform: translateY(-2px);
        }

        .browse-item .name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .browse-item .meta {
            font-size: 12px;
            color: var(--text-dim);
        }

        .browse-item .year {
            font-size: 11px;
            color: var(--accent);
            margin-top: 3px;
        }

        /* Playlist Submenu */
        .playlist-submenu {
            position: fixed;
            background: var(--bg-medium);
            border: 1px solid var(--bg-light);
            border-radius: 4px;
            padding: 5px 0;
            min-width: 150px;
            max-height: 200px;
            overflow-y: auto;
            display: none;
            z-index: 1001;
        }

        .playlist-submenu.show {
            display: block;
        }

        .playlist-submenu-item {
            padding: 8px 15px;
            cursor: pointer;
            font-size: 13px;
        }

        .playlist-submenu-item:hover {
            background: var(--bg-light);
        }

        /* BOTTOM - Stats */
        #bottom-bar {
            background: var(--bg-medium);
            padding: 10px 20px;
            font-size: 12px;
            color: var(--text-dim);
            display: flex;
            justify-content: space-between;
            border-top: 1px solid var(--bg-light);
        }

        .stat-item {
            display: flex;
            gap: 5px;
        }

        .stat-item span {
            color: var(--text);
        }

        /* Context Menu */
        .context-menu {
            position: fixed;
            background: var(--bg-medium);
            border: 1px solid var(--bg-light);
            border-radius: 4px;
            padding: 5px 0;
            min-width: 150px;
            display: none;
            z-index: 1000;
        }

        .context-menu.show {
            display: block;
        }

        .context-menu-item {
            padding: 8px 15px;
            cursor: pointer;
            font-size: 13px;
        }

        .context-menu-item:hover {
            background: var(--bg-light);
        }

        /* Top Actions */
        .top-actions {
            display: flex;
            gap: 8px;
        }

        .top-actions button {
            background: var(--bg-light);
            border: none;
            color: var(--text);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.2s;
        }

        .top-actions button:hover {
            background: var(--accent);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: var(--bg-medium);
            border-radius: 12px;
            padding: 30px;
            position: relative;
            max-width: 90%;
            max-height: 90%;
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            color: var(--text-dim);
            font-size: 24px;
            cursor: pointer;
        }

        .modal-close:hover {
            color: var(--text);
        }

        /* Now Playing Modal */
        .now-playing-content {
            text-align: center;
            width: 450px;
        }

        .now-playing-artwork {
            margin-bottom: 20px;
        }

        .now-playing-artwork img {
            width: 300px;
            height: 300px;
            object-fit: cover;
            border-radius: 8px;
        }

        .artwork-placeholder {
            width: 300px;
            height: 300px;
            background: var(--bg-light);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 80px;
            color: var(--text-dim);
            margin: 0 auto;
        }

        .now-playing-info {
            margin-bottom: 20px;
        }

        .np-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .np-artist {
            font-size: 18px;
            color: var(--accent);
            margin-bottom: 4px;
        }

        .np-album {
            font-size: 14px;
            color: var(--text-dim);
        }

        #visualizer {
            width: 100%;
            height: 100px;
            border-radius: 8px;
            background: var(--bg-dark);
        }

        /* Keyboard Shortcuts Modal */
        .shortcuts-content {
            width: 400px;
        }

        .shortcuts-content h2 {
            margin-bottom: 20px;
            text-align: center;
        }

        .shortcuts-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .shortcut-item {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 14px;
        }

        .shortcut-item kbd {
            background: var(--bg-light);
            padding: 5px 12px;
            border-radius: 4px;
            font-family: monospace;
            min-width: 50px;
            text-align: center;
        }

        /* Queue View Styles */
        .queue-item {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 4px;
            background: var(--bg-medium);
        }

        .queue-item:hover {
            background: var(--bg-light);
        }

        .queue-item .queue-num {
            width: 30px;
            color: var(--text-dim);
            font-size: 14px;
        }

        .queue-item .queue-info {
            flex: 1;
        }

        .queue-item .queue-title {
            font-weight: 500;
        }

        .queue-item .queue-artist {
            font-size: 12px;
            color: var(--text-dim);
        }

        .queue-item .queue-remove {
            background: none;
            border: none;
            color: var(--text-dim);
            cursor: pointer;
            font-size: 18px;
            padding: 5px;
        }

        .queue-item .queue-remove:hover {
            color: var(--accent);
        }

        .queue-empty {
            text-align: center;
            color: var(--text-dim);
            padding: 40px;
        }

        /* Drag and Drop */
        .draggable {
            cursor: grab;
        }

        .draggable:active {
            cursor: grabbing;
        }

        .dragging {
            opacity: 0.5;
            background: var(--bg-light) !important;
        }

        .drag-over {
            border-top: 2px solid var(--accent);
        }

        .drag-handle {
            cursor: grab;
            color: var(--text-dim);
            padding: 0 10px;
            font-size: 14px;
        }

        .drag-handle:hover {
            color: var(--text);
        }
    </style>
</head>
<body>
    <!-- TOP BAR -->
    <div id="top-bar">
        <div class="controls">
            <button id="prev-btn" title="Previous">&#9198;</button>
            <button id="play-btn" class="play-btn" title="Play">&#9654;</button>
            <button id="next-btn" title="Next">&#9197;</button>
        </div>

        <div class="volume-control">
            <input type="range" id="volume" min="0" max="100" value="80">
        </div>

        <div class="track-info">
            <div class="title" id="current-title">No track selected</div>
            <div class="artist" id="current-artist">-</div>
        </div>

        <div class="progress-container">
            <span class="time" id="current-time">0:00</span>
            <div class="progress-bar" id="progress-bar">
                <div class="progress" id="progress"></div>
            </div>
            <span class="time" id="duration">0:00</span>
        </div>

        <div class="search-box">
            <input type="text" id="search" placeholder="Search library...">
        </div>

        <div class="top-actions">
            <button id="now-playing-btn" title="Now Playing">&#9835;</button>
            <button id="shortcuts-btn" title="Keyboard Shortcuts">?</button>
        </div>
    </div>

    <!-- MIDDLE -->
    <div id="middle">
        <!-- SIDEBAR -->
        <div id="sidebar">
            <h3>Library</h3>
            <div class="nav-item active" data-view="all">All Songs</div>
            <div class="nav-item" data-view="artists">Artists</div>
            <div class="nav-item" data-view="albums">Albums</div>
            <div class="nav-item" data-view="genres">Genres</div>
            <div class="nav-item" data-view="recently_played">Recently Played</div>
            <div class="nav-item" data-view="most_played">Most Played</div>
            <div class="nav-item" data-view="queue">Play Queue</div>

            <h3 style="margin-top: 20px;">Playlists</h3>
            <div id="playlist-list"></div>
            <button class="new-playlist" id="new-playlist-btn">+ New Playlist</button>
        </div>

        <!-- MAIN -->
        <div id="main">
            <h2 id="view-title">All Songs</h2>
            <div class="track-list" id="track-list"></div>
        </div>
    </div>

    <!-- BOTTOM BAR -->
    <div id="bottom-bar">
        <div class="stat-item">Tracks: <span id="stat-tracks"><?= $stats['total_tracks'] ?></span></div>
        <div class="stat-item">Artists: <span id="stat-artists"><?= $stats['total_artists'] ?></span></div>
        <div class="stat-item">Albums: <span id="stat-albums"><?= $stats['total_albums'] ?></span></div>
        <div class="stat-item">Genres: <span id="stat-genres"><?= $stats['total_genres'] ?></span></div>
        <div class="stat-item">Duration: <span id="stat-duration"><?= formatDuration($stats['total_duration']) ?></span></div>
        <div class="stat-item">Plays: <span id="stat-plays"><?= $stats['total_plays'] ?></span></div>
        <div class="stat-item">Size: <span id="stat-size"><?= formatBytes($stats['total_size']) ?></span></div>
        <button id="scan-btn" style="background: var(--bg-light); border: none; color: var(--text); padding: 5px 10px; border-radius: 4px; cursor: pointer;">Scan Library</button>
    </div>

    <!-- Context Menu for Tracks -->
    <div class="context-menu" id="context-menu">
        <div class="context-menu-item" data-action="play-next">Play Next</div>
        <div class="context-menu-item" data-action="add-to-queue">Add to Queue</div>
        <div class="context-menu-item" data-action="add-to-playlist">Add to Playlist</div>
        <div class="context-menu-item" data-action="remove-from-playlist" style="display:none;">Remove from Playlist</div>
    </div>

    <!-- Context Menu for Playlists -->
    <div class="context-menu" id="playlist-context-menu">
        <div class="context-menu-item" data-action="rename-playlist">Rename</div>
        <div class="context-menu-item" data-action="delete-playlist">Delete</div>
    </div>

    <!-- Playlist Submenu -->
    <div class="playlist-submenu" id="playlist-submenu"></div>

    <!-- Now Playing Modal -->
    <div class="modal" id="now-playing-modal">
        <div class="modal-content now-playing-content">
            <button class="modal-close" id="now-playing-close">&times;</button>
            <div class="now-playing-artwork">
                <div class="artwork-placeholder" id="now-playing-art">&#9835;</div>
            </div>
            <div class="now-playing-info">
                <div class="np-title" id="np-title">No track playing</div>
                <div class="np-artist" id="np-artist">-</div>
                <div class="np-album" id="np-album">-</div>
            </div>
            <canvas id="visualizer" width="400" height="100"></canvas>
        </div>
    </div>

    <!-- Keyboard Shortcuts Modal -->
    <div class="modal" id="shortcuts-modal">
        <div class="modal-content shortcuts-content">
            <button class="modal-close" id="shortcuts-close">&times;</button>
            <h2>Keyboard Shortcuts</h2>
            <div class="shortcuts-list">
                <div class="shortcut-item"><kbd>Space</kbd> Play / Pause</div>
                <div class="shortcut-item"><kbd>&larr;</kbd> Previous track</div>
                <div class="shortcut-item"><kbd>&rarr;</kbd> Next track</div>
                <div class="shortcut-item"><kbd>&uarr;</kbd> Volume up</div>
                <div class="shortcut-item"><kbd>&darr;</kbd> Volume down</div>
                <div class="shortcut-item"><kbd>M</kbd> Mute / Unmute</div>
                <div class="shortcut-item"><kbd>N</kbd> Now Playing view</div>
                <div class="shortcut-item"><kbd>?</kbd> Show this help</div>
            </div>
        </div>
    </div>

    <script>
        // Audio Player with Crossfade using Web Audio API
        class AudioPlayer {
            constructor() {
                this.audioContext = null;
                this.masterGain = null;
                this.analyser = null;
                this.volume = 0.8;
                this.crossfadeDuration = 3; // seconds

                // Two decks for crossfading (like a DJ mixer)
                this.decks = [
                    { audio: new Audio(), gainNode: null, source: null },
                    { audio: new Audio(), gainNode: null, source: null }
                ];
                this.activeDeck = 0;

                this.tracks = [];
                this.queue = []; // Play queue
                this.currentIndex = -1;
                this.currentTrack = null;
                this.isPlaying = false;
                this.isCrossfading = false;
                this.isMuted = false;
                this.previousVolume = 0.8;

                // Set up event listeners for both decks
                this.decks.forEach((deck, i) => {
                    deck.audio.addEventListener('timeupdate', () => {
                        if (i === this.activeDeck) {
                            this.updateProgress();
                            this.checkCrossfade();
                        }
                    });
                    deck.audio.addEventListener('loadedmetadata', () => {
                        if (i === this.activeDeck) {
                            this.updateDuration();
                        }
                    });
                    deck.audio.addEventListener('ended', () => {
                        if (i === this.activeDeck && !this.isCrossfading) {
                            this.next();
                        }
                    });
                });
            }

            initAudioContext() {
                if (!this.audioContext) {
                    this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    this.masterGain = this.audioContext.createGain();
                    this.analyser = this.audioContext.createAnalyser();
                    this.analyser.fftSize = 256;
                    this.masterGain.connect(this.analyser);
                    this.analyser.connect(this.audioContext.destination);
                    this.masterGain.gain.value = this.volume;
                }
            }

            connectDeck(deckIndex) {
                const deck = this.decks[deckIndex];
                if (!deck.source) {
                    deck.source = this.audioContext.createMediaElementSource(deck.audio);
                    deck.gainNode = this.audioContext.createGain();
                    deck.source.connect(deck.gainNode);
                    deck.gainNode.connect(this.masterGain);
                }
            }

            getActiveDeck() {
                return this.decks[this.activeDeck];
            }

            getInactiveDeck() {
                return this.decks[1 - this.activeDeck];
            }

            loadTrack(track, index, startPlaying = false) {
                this.initAudioContext();

                // If crossfading, load to inactive deck; otherwise use active deck
                const targetDeck = this.isCrossfading ? this.getInactiveDeck() : this.getActiveDeck();
                const deckIndex = this.isCrossfading ? 1 - this.activeDeck : this.activeDeck;

                this.connectDeck(deckIndex);

                this.currentIndex = index;
                this.currentTrack = track;
                targetDeck.audio.src = 'music/' + track.path;

                document.getElementById('current-title').textContent = track.title || track.filename;
                document.getElementById('current-artist').textContent = track.artist || 'Unknown';

                // Update playing state in UI
                document.querySelectorAll('.track-row').forEach((row, i) => {
                    row.classList.toggle('playing', i === index);
                });

                // Increment play count
                fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=play_count&track_id=${track.id}`
                });

                if (startPlaying) {
                    targetDeck.gainNode.gain.value = this.isCrossfading ? 0 : this.volume;
                    targetDeck.audio.play();
                }
            }

            play() {
                this.initAudioContext();
                if (this.currentIndex === -1 && this.tracks.length > 0) {
                    this.loadTrack(this.tracks[0], 0);
                }
                this.connectDeck(this.activeDeck);
                const deck = this.getActiveDeck();
                deck.gainNode.gain.value = this.volume;
                deck.audio.play();
                this.isPlaying = true;
                document.getElementById('play-btn').innerHTML = '&#10074;&#10074;';
            }

            pause() {
                this.decks.forEach(deck => deck.audio.pause());
                this.isPlaying = false;
                document.getElementById('play-btn').innerHTML = '&#9654;';
            }

            toggle() {
                this.isPlaying ? this.pause() : this.play();
            }

            prev() {
                if (this.currentIndex > 0) {
                    this.isCrossfading = false;
                    this.stopInactiveDeck();
                    this.loadTrack(this.tracks[this.currentIndex - 1], this.currentIndex - 1);
                    this.play();
                }
            }

            next() {
                // Check queue first
                if (this.queue.length > 0) {
                    const nextTrack = this.queue.shift();
                    this.isCrossfading = false;
                    this.stopInactiveDeck();
                    // Find track index in current tracks list, or use -1
                    const idx = this.tracks.findIndex(t => t.id === nextTrack.id);
                    this.loadTrack(nextTrack, idx >= 0 ? idx : this.currentIndex);
                    this.play();
                    updateQueueDisplay();
                    return;
                }

                if (this.currentIndex < this.tracks.length - 1) {
                    if (!this.isCrossfading) {
                        this.isCrossfading = false;
                        this.stopInactiveDeck();
                        this.loadTrack(this.tracks[this.currentIndex + 1], this.currentIndex + 1);
                        this.play();
                    }
                } else {
                    // End of playlist
                    this.pause();
                }
            }

            playNext(track) {
                this.queue.unshift(track);
                updateQueueDisplay();
            }

            addToQueue(track) {
                this.queue.push(track);
                updateQueueDisplay();
            }

            removeFromQueue(index) {
                this.queue.splice(index, 1);
                updateQueueDisplay();
            }

            clearQueue() {
                this.queue = [];
                updateQueueDisplay();
            }

            toggleMute() {
                if (this.isMuted) {
                    this.setVolume(this.previousVolume * 100);
                    this.isMuted = false;
                } else {
                    this.previousVolume = this.volume;
                    this.setVolume(0);
                    this.isMuted = true;
                }
                document.getElementById('volume').value = this.isMuted ? 0 : this.previousVolume * 100;
            }

            stopInactiveDeck() {
                const deck = this.getInactiveDeck();
                deck.audio.pause();
                deck.audio.currentTime = 0;
                if (deck.gainNode) {
                    deck.gainNode.gain.value = 0;
                }
            }

            checkCrossfade() {
                const deck = this.getActiveDeck();
                const timeRemaining = deck.audio.duration - deck.audio.currentTime;

                // Start crossfade when approaching end of track
                // Check if there's a queued track OR next track in list
                const hasNext = this.queue.length > 0 || this.currentIndex < this.tracks.length - 1;

                if (timeRemaining <= this.crossfadeDuration &&
                    timeRemaining > 0 &&
                    !this.isCrossfading &&
                    hasNext &&
                    this.isPlaying) {
                    this.startCrossfade();
                }
            }

            startCrossfade() {
                this.isCrossfading = true;

                // Check queue first, then fall back to tracks list
                let nextTrack;
                let nextIndex;

                if (this.queue.length > 0) {
                    nextTrack = this.queue.shift();
                    nextIndex = this.tracks.findIndex(t => t.id === nextTrack.id);
                    if (nextIndex < 0) nextIndex = this.currentIndex;
                    updateQueueDisplay();
                } else {
                    nextIndex = this.currentIndex + 1;
                    nextTrack = this.tracks[nextIndex];
                }

                // Load next track on inactive deck
                const inactiveDeckIndex = 1 - this.activeDeck;
                this.connectDeck(inactiveDeckIndex);
                const inactiveDeck = this.getInactiveDeck();
                const activeDeck = this.getActiveDeck();

                inactiveDeck.audio.src = 'music/' + nextTrack.path;
                inactiveDeck.gainNode.gain.value = 0;
                inactiveDeck.audio.play();

                // Crossfade using Web Audio API gain automation
                const now = this.audioContext.currentTime;
                const fadeTime = this.crossfadeDuration;

                // Fade out active deck
                activeDeck.gainNode.gain.setValueAtTime(this.volume, now);
                activeDeck.gainNode.gain.linearRampToValueAtTime(0, now + fadeTime);

                // Fade in inactive deck
                inactiveDeck.gainNode.gain.setValueAtTime(0, now);
                inactiveDeck.gainNode.gain.linearRampToValueAtTime(this.volume, now + fadeTime);

                // Switch active deck after crossfade
                setTimeout(() => {
                    activeDeck.audio.pause();
                    activeDeck.audio.currentTime = 0;
                    this.activeDeck = inactiveDeckIndex;
                    this.currentIndex = nextIndex;
                    this.currentTrack = nextTrack;
                    this.isCrossfading = false;

                    // Update UI
                    document.getElementById('current-title').textContent = nextTrack.title || nextTrack.filename;
                    document.getElementById('current-artist').textContent = nextTrack.artist || 'Unknown';
                    document.querySelectorAll('.track-row').forEach((row, i) => {
                        row.classList.toggle('playing', i === nextIndex);
                    });

                    // Update duration display for new track
                    this.updateDuration();

                    // Increment play count for new track
                    fetch('', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `action=play_count&track_id=${nextTrack.id}`
                    });
                }, fadeTime * 1000);
            }

            setVolume(value) {
                this.volume = value / 100;
                if (this.masterGain) {
                    this.masterGain.gain.value = this.volume;
                }
            }

            seek(percent) {
                const deck = this.getActiveDeck();
                if (deck.audio.duration) {
                    deck.audio.currentTime = (percent / 100) * deck.audio.duration;
                }
            }

            updateProgress() {
                const deck = this.getActiveDeck();
                const percent = (deck.audio.currentTime / deck.audio.duration) * 100 || 0;
                document.getElementById('progress').style.width = percent + '%';
                document.getElementById('current-time').textContent = this.formatTime(deck.audio.currentTime);
            }

            updateDuration() {
                const deck = this.getActiveDeck();
                const actualDuration = Math.floor(deck.audio.duration);
                document.getElementById('duration').textContent = this.formatTime(actualDuration);

                // Save actual duration to database if different from stored value
                if (this.currentTrack && actualDuration > 0) {
                    const storedDuration = parseInt(this.currentTrack.duration) || 0;
                    if (Math.abs(storedDuration - actualDuration) > 1) {
                        this.currentTrack.duration = actualDuration;
                        fetch('', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: `action=update_duration&track_id=${this.currentTrack.id}&duration=${actualDuration}`
                        });
                        // Update the duration in the track list display
                        const row = document.querySelector(`.track-row[data-id="${this.currentTrack.id}"] .duration`);
                        if (row) {
                            const mins = Math.floor(actualDuration / 60);
                            const secs = Math.floor(actualDuration % 60);
                            row.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
                        }
                    }
                }
            }

            formatTime(seconds) {
                const mins = Math.floor(seconds / 60);
                const secs = Math.floor(seconds % 60);
                return `${mins}:${secs.toString().padStart(2, '0')}`;
            }
        }

        // Initialize player
        const player = new AudioPlayer();

        // API helper
        async function api(action, data = {}) {
            const body = new URLSearchParams({action, ...data});
            const response = await fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body
            });
            return response.json();
        }


        // Format duration from seconds to mm:ss
        function formatDuration(seconds) {
            if (!seconds || seconds <= 0) return '-';
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        }

        // Current view state
        let currentView = 'all';
        let currentPlaylistId = null;

        // Render track list
        function renderTracks(tracks, isPlaylist = false) {
            const container = document.getElementById('track-list');
            const draggable = isPlaylist ? 'draggable="true"' : '';
            const dragClass = isPlaylist ? ' draggable' : '';

            container.innerHTML = tracks.map((track, index) => `
                <div class="track-row${dragClass}" data-index="${index}" data-id="${track.id}" ${draggable}>
                    ${isPlaylist ? '<div class="drag-handle">&#9776;</div>' : ''}
                    <div class="num">${track.track_number || (index + 1)}</div>
                    <div class="track-title">${escapeHtml(track.title || track.filename)}</div>
                    <div class="track-artist">${escapeHtml(track.artist || 'Unknown')}</div>
                    <div class="track-album">${escapeHtml(track.album || '')}</div>
                    <div class="track-year">${track.year || '-'}</div>
                    <div class="duration">${formatDuration(track.duration)}</div>
                    <button class="menu-btn" title="More options">&#8942;</button>
                </div>
            `).join('');

            if (isPlaylist) {
                initPlaylistDragDrop();
            }
        }

        // Playlist track drag and drop
        function initPlaylistDragDrop() {
            const items = document.querySelectorAll('.track-row.draggable');
            let draggedItem = null;

            items.forEach(item => {
                item.addEventListener('dragstart', (e) => {
                    draggedItem = item;
                    item.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                });

                item.addEventListener('dragend', () => {
                    item.classList.remove('dragging');
                    items.forEach(i => i.classList.remove('drag-over'));
                    draggedItem = null;
                });

                item.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    if (draggedItem && draggedItem !== item) {
                        item.classList.add('drag-over');
                    }
                });

                item.addEventListener('dragleave', () => {
                    item.classList.remove('drag-over');
                });

                item.addEventListener('drop', async (e) => {
                    e.preventDefault();
                    item.classList.remove('drag-over');
                    if (draggedItem && draggedItem !== item && currentPlaylistId) {
                        const fromIndex = parseInt(draggedItem.dataset.index);
                        const toIndex = parseInt(item.dataset.index);

                        // Reorder tracks array
                        const [moved] = player.tracks.splice(fromIndex, 1);
                        player.tracks.splice(toIndex, 0, moved);

                        // Get new track ID order
                        const trackIds = player.tracks.map(t => t.id);

                        // Save to database
                        await api('reorder_playlist', {
                            playlist_id: currentPlaylistId,
                            track_ids: JSON.stringify(trackIds)
                        });

                        // Re-render
                        renderTracks(player.tracks, true);
                    }
                });
            });
        }

        // Render artists grid
        function renderArtists(artists) {
            const container = document.getElementById('track-list');
            container.innerHTML = `<div class="browse-grid">${artists.map(artist => `
                <div class="browse-item" data-artist="${escapeHtml(artist.artist)}">
                    <div class="name">${escapeHtml(artist.artist)}</div>
                    <div class="meta">${artist.track_count} tracks &bull; ${artist.album_count} albums</div>
                </div>
            `).join('')}</div>`;
        }

        // Render albums grid
        function renderAlbums(albums) {
            const container = document.getElementById('track-list');
            container.innerHTML = `<div class="browse-grid">${albums.map(album => `
                <div class="browse-item" data-album="${escapeHtml(album.album)}" data-artist="${escapeHtml(album.artist)}">
                    <div class="name">${escapeHtml(album.album)}</div>
                    <div class="meta">${escapeHtml(album.artist)}</div>
                    <div class="meta">${album.track_count} tracks</div>
                    ${album.year ? `<div class="year">${album.year}</div>` : ''}
                </div>
            `).join('')}</div>`;
        }

        // Render genres grid
        function renderGenres(genres) {
            const container = document.getElementById('track-list');
            container.innerHTML = `<div class="browse-grid">${genres.map(genre => `
                <div class="browse-item" data-genre="${escapeHtml(genre.genre)}">
                    <div class="name">${escapeHtml(genre.genre)}</div>
                    <div class="meta">${genre.track_count} tracks &bull; ${genre.artist_count} artists</div>
                </div>
            `).join('')}</div>`;
        }

        // Load view based on type
        async function loadView(view) {
            currentView = view;
            currentPlaylistId = null; // Reset playlist context
            const title = document.getElementById('view-title');

            switch(view) {
                case 'all':
                    title.textContent = 'All Songs';
                    const allData = await api('tracks');
                    player.tracks = allData.tracks;
                    renderTracks(allData.tracks);
                    break;
                case 'artists':
                    title.textContent = 'Artists';
                    const artistsData = await api('artists');
                    renderArtists(artistsData.artists);
                    break;
                case 'albums':
                    title.textContent = 'Albums';
                    const albumsData = await api('albums');
                    renderAlbums(albumsData.albums);
                    break;
                case 'genres':
                    title.textContent = 'Genres';
                    const genresData = await api('genres');
                    renderGenres(genresData.genres);
                    break;
                case 'recently_played':
                    title.textContent = 'Recently Played';
                    const recentData = await api('recently_played');
                    player.tracks = recentData.tracks;
                    renderTracks(recentData.tracks);
                    break;
                case 'most_played':
                    title.textContent = 'Most Played';
                    const mostData = await api('most_played');
                    player.tracks = mostData.tracks;
                    renderTracks(mostData.tracks);
                    break;
                case 'queue':
                    title.textContent = 'Play Queue';
                    renderQueue();
                    break;
            }
        }

        // Load tracks filtered by artist/album/genre
        async function loadFilteredTracks(filter) {
            const data = await api('tracks', filter);
            player.tracks = data.tracks;
            renderTracks(data.tracks);
        }

        // HTML escape helper
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Load playlists
        async function loadPlaylists() {
            const data = await api('playlists');
            const container = document.getElementById('playlist-list');
            container.innerHTML = data.playlists.map(pl => `
                <div class="playlist-item" data-id="${pl.id}">${escapeHtml(pl.name)} (${pl.track_count})</div>
            `).join('');
        }

        // Format duration for stats (hours/minutes)
        function formatStatsDuration(seconds) {
            if (!seconds || seconds <= 0) return '0m';
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            if (hours > 0) {
                return `${hours}h ${minutes}m`;
            }
            return `${minutes}m`;
        }

        // Update stats
        async function updateStats() {
            const data = await api('stats');
            document.getElementById('stat-tracks').textContent = data.stats.total_tracks;
            document.getElementById('stat-artists').textContent = data.stats.total_artists;
            document.getElementById('stat-albums').textContent = data.stats.total_albums;
            document.getElementById('stat-genres').textContent = data.stats.total_genres;
            document.getElementById('stat-duration').textContent = formatStatsDuration(data.stats.total_duration);
            document.getElementById('stat-plays').textContent = data.stats.total_plays;
        }

        // Event listeners
        document.getElementById('play-btn').addEventListener('click', () => player.toggle());
        document.getElementById('prev-btn').addEventListener('click', () => player.prev());
        document.getElementById('next-btn').addEventListener('click', () => player.next());
        document.getElementById('volume').addEventListener('input', (e) => player.setVolume(e.target.value));

        document.getElementById('progress-bar').addEventListener('click', (e) => {
            const rect = e.target.getBoundingClientRect();
            const percent = ((e.clientX - rect.left) / rect.width) * 100;
            player.seek(percent);
        });

        let searchTimeout;
        document.getElementById('search').addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(async () => {
                document.getElementById('view-title').textContent = e.target.value ? `Search: ${e.target.value}` : 'All Songs';
                const data = await api('tracks', {search: e.target.value});
                player.tracks = data.tracks;
                renderTracks(data.tracks);
            }, 300);
        });

        document.getElementById('scan-btn').addEventListener('click', async () => {
            await api('scan');
            await loadView(currentView);
            await updateStats();
        });

        document.getElementById('new-playlist-btn').addEventListener('click', async () => {
            const name = prompt('Playlist name:');
            if (name) {
                await api('create_playlist', {name});
                await loadPlaylists();
            }
        });

        // Playlist click handler
        document.getElementById('playlist-list').addEventListener('click', async (e) => {
            const item = e.target.closest('.playlist-item');
            if (item) {
                currentPlaylistId = item.dataset.id;
                currentView = 'playlist';
                const data = await api('playlist_tracks', {playlist_id: item.dataset.id});
                player.tracks = data.tracks;
                renderTracks(data.tracks, true); // Enable drag-drop for playlists
                document.getElementById('view-title').textContent = item.textContent.split(' (')[0];

                // Update active state
                document.querySelectorAll('.nav-item, .playlist-item').forEach(i => i.classList.remove('active'));
                item.classList.add('active');
            }
        });

        // Nav items
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', () => {
                document.querySelectorAll('.nav-item, .playlist-item').forEach(i => i.classList.remove('active'));
                item.classList.add('active');
                loadView(item.dataset.view);
            });
        });

        // Browse grid click handler (for artists, albums, genres)
        document.getElementById('track-list').addEventListener('click', (e) => {
            const browseItem = e.target.closest('.browse-item');
            if (browseItem) {
                const artist = browseItem.dataset.artist;
                const album = browseItem.dataset.album;
                const genre = browseItem.dataset.genre;

                if (genre) {
                    document.getElementById('view-title').textContent = genre;
                    loadFilteredTracks({genre});
                } else if (album) {
                    document.getElementById('view-title').textContent = album;
                    loadFilteredTracks({album});
                } else if (artist) {
                    document.getElementById('view-title').textContent = artist;
                    loadFilteredTracks({artist});
                }
                return;
            }

            const row = e.target.closest('.track-row');
            if (row && !e.target.classList.contains('menu-btn')) {
                const index = parseInt(row.dataset.index);
                player.loadTrack(player.tracks[index], index);
                player.play();
            }
        });

        // Context menu for adding to playlist
        let contextTrackId = null;
        let playlistsCache = [];

        document.getElementById('track-list').addEventListener('contextmenu', async (e) => {
            e.preventDefault();
            const row = e.target.closest('.track-row');
            if (row) {
                contextTrackId = row.dataset.id;
                const menu = document.getElementById('context-menu');
                menu.style.left = e.pageX + 'px';
                menu.style.top = e.pageY + 'px';
                menu.classList.add('show');

                // Show/hide "Remove from Playlist" based on context
                const removeItem = menu.querySelector('[data-action="remove-from-playlist"]');
                removeItem.style.display = currentPlaylistId ? 'block' : 'none';

                // Cache playlists for submenu
                const data = await api('playlists');
                playlistsCache = data.playlists;
            }
        });

        // Context menu item click - show playlist submenu
        document.querySelector('[data-action="add-to-playlist"]').addEventListener('click', (e) => {
            e.stopPropagation();
            const contextMenu = document.getElementById('context-menu');
            const submenu = document.getElementById('playlist-submenu');

            if (playlistsCache.length === 0) {
                alert('No playlists. Create one first!');
                contextMenu.classList.remove('show');
                return;
            }

            submenu.innerHTML = playlistsCache.map(pl => `
                <div class="playlist-submenu-item" data-playlist-id="${pl.id}">${escapeHtml(pl.name)}</div>
            `).join('');

            const rect = contextMenu.getBoundingClientRect();
            submenu.style.left = (rect.right + 5) + 'px';
            submenu.style.top = rect.top + 'px';
            submenu.classList.add('show');
        });

        // Playlist submenu click - add track to playlist
        document.getElementById('playlist-submenu').addEventListener('click', async (e) => {
            const item = e.target.closest('.playlist-submenu-item');
            if (item && contextTrackId) {
                await api('add_to_playlist', {
                    playlist_id: item.dataset.playlistId,
                    track_id: contextTrackId
                });
                await loadPlaylists();
                document.getElementById('context-menu').classList.remove('show');
                document.getElementById('playlist-submenu').classList.remove('show');
            }
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.context-menu') && !e.target.closest('.playlist-submenu')) {
                document.getElementById('context-menu').classList.remove('show');
                document.getElementById('playlist-submenu').classList.remove('show');
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT') return;
            if (e.code === 'Space') { e.preventDefault(); player.toggle(); }
            if (e.code === 'ArrowLeft') player.prev();
            if (e.code === 'ArrowRight') player.next();
            if (e.code === 'ArrowUp') { e.preventDefault(); adjustVolume(5); }
            if (e.code === 'ArrowDown') { e.preventDefault(); adjustVolume(-5); }
            if (e.code === 'KeyM') player.toggleMute();
            if (e.code === 'KeyN') toggleNowPlaying();
            if (e.key === '?') toggleShortcuts();
        });

        // Volume adjustment helper
        function adjustVolume(delta) {
            const vol = document.getElementById('volume');
            const newVal = Math.max(0, Math.min(100, parseInt(vol.value) + delta));
            vol.value = newVal;
            player.setVolume(newVal);
        }

        // Queue display update
        function updateQueueDisplay() {
            if (currentView === 'queue') {
                renderQueue();
            }
        }

        // Render play queue
        function renderQueue() {
            const container = document.getElementById('track-list');
            if (player.queue.length === 0) {
                container.innerHTML = '<div class="queue-empty">Queue is empty. Right-click a track to add it.</div>';
                return;
            }
            container.innerHTML = player.queue.map((track, index) => `
                <div class="queue-item draggable" data-index="${index}" draggable="true">
                    <div class="drag-handle">&#9776;</div>
                    <div class="queue-num">${index + 1}</div>
                    <div class="queue-info">
                        <div class="queue-title">${escapeHtml(track.title || track.filename)}</div>
                        <div class="queue-artist">${escapeHtml(track.artist || 'Unknown')}</div>
                    </div>
                    <button class="queue-remove" title="Remove from queue">&times;</button>
                </div>
            `).join('');
            initQueueDragDrop();
        }

        // Queue drag and drop
        function initQueueDragDrop() {
            const items = document.querySelectorAll('.queue-item.draggable');
            let draggedItem = null;

            items.forEach(item => {
                item.addEventListener('dragstart', (e) => {
                    draggedItem = item;
                    item.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                });

                item.addEventListener('dragend', () => {
                    item.classList.remove('dragging');
                    items.forEach(i => i.classList.remove('drag-over'));
                    draggedItem = null;
                });

                item.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    if (draggedItem && draggedItem !== item) {
                        item.classList.add('drag-over');
                    }
                });

                item.addEventListener('dragleave', () => {
                    item.classList.remove('drag-over');
                });

                item.addEventListener('drop', (e) => {
                    e.preventDefault();
                    item.classList.remove('drag-over');
                    if (draggedItem && draggedItem !== item) {
                        const fromIndex = parseInt(draggedItem.dataset.index);
                        const toIndex = parseInt(item.dataset.index);
                        // Reorder queue array
                        const [moved] = player.queue.splice(fromIndex, 1);
                        player.queue.splice(toIndex, 0, moved);
                        renderQueue();
                    }
                });
            });
        }

        // Queue item click handlers
        document.getElementById('track-list').addEventListener('click', (e) => {
            const removeBtn = e.target.closest('.queue-remove');
            if (removeBtn) {
                const item = removeBtn.closest('.queue-item');
                if (item) {
                    player.removeFromQueue(parseInt(item.dataset.index));
                }
                return;
            }

            // ... existing handlers continue
        });

        // Now Playing modal
        function toggleNowPlaying() {
            const modal = document.getElementById('now-playing-modal');
            modal.classList.toggle('show');
            if (modal.classList.contains('show')) {
                updateNowPlayingView();
                startVisualizer();
            } else {
                stopVisualizer();
            }
        }

        async function updateNowPlayingView() {
            const track = player.currentTrack;
            if (!track) return;

            document.getElementById('np-title').textContent = track.title || track.filename;
            document.getElementById('np-artist').textContent = track.artist || 'Unknown';
            document.getElementById('np-album').textContent = track.album || '';

            // Fetch artwork
            const artContainer = document.getElementById('now-playing-art');
            try {
                const data = await api('artwork', {path: track.path});
                if (data.artwork) {
                    artContainer.innerHTML = `<img src="${data.artwork}" alt="Album Art">`;
                } else {
                    artContainer.innerHTML = '&#9835;';
                    artContainer.className = 'artwork-placeholder';
                }
            } catch (e) {
                artContainer.innerHTML = '&#9835;';
                artContainer.className = 'artwork-placeholder';
            }
        }

        // Visualizer
        let visualizerAnimation = null;

        function startVisualizer() {
            if (!player.analyser) return;

            const canvas = document.getElementById('visualizer');
            const ctx = canvas.getContext('2d');
            const bufferLength = player.analyser.frequencyBinCount;
            const dataArray = new Uint8Array(bufferLength);

            function draw() {
                visualizerAnimation = requestAnimationFrame(draw);
                player.analyser.getByteFrequencyData(dataArray);

                ctx.fillStyle = '#1a1a2e';
                ctx.fillRect(0, 0, canvas.width, canvas.height);

                const barWidth = (canvas.width / bufferLength) * 2.5;
                let x = 0;

                for (let i = 0; i < bufferLength; i++) {
                    const barHeight = (dataArray[i] / 255) * canvas.height;

                    const hue = (i / bufferLength) * 60 + 340; // Pink to red gradient
                    ctx.fillStyle = `hsl(${hue}, 70%, 50%)`;
                    ctx.fillRect(x, canvas.height - barHeight, barWidth, barHeight);

                    x += barWidth + 1;
                }
            }

            draw();
        }

        function stopVisualizer() {
            if (visualizerAnimation) {
                cancelAnimationFrame(visualizerAnimation);
                visualizerAnimation = null;
            }
        }

        // Shortcuts modal
        function toggleShortcuts() {
            document.getElementById('shortcuts-modal').classList.toggle('show');
        }

        // Modal close handlers
        document.getElementById('now-playing-close').addEventListener('click', toggleNowPlaying);
        document.getElementById('shortcuts-close').addEventListener('click', toggleShortcuts);
        document.getElementById('now-playing-btn').addEventListener('click', toggleNowPlaying);
        document.getElementById('shortcuts-btn').addEventListener('click', toggleShortcuts);

        // Close modals on backdrop click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('show');
                    if (modal.id === 'now-playing-modal') {
                        stopVisualizer();
                    }
                }
            });
        });

        // Context menu handlers for Play Next / Add to Queue
        document.querySelector('[data-action="play-next"]').addEventListener('click', () => {
            if (contextTrackId) {
                const track = player.tracks.find(t => t.id == contextTrackId);
                if (track) {
                    player.playNext(track);
                }
            }
            document.getElementById('context-menu').classList.remove('show');
        });

        document.querySelector('[data-action="add-to-queue"]').addEventListener('click', () => {
            if (contextTrackId) {
                const track = player.tracks.find(t => t.id == contextTrackId);
                if (track) {
                    player.addToQueue(track);
                }
            }
            document.getElementById('context-menu').classList.remove('show');
        });

        document.querySelector('[data-action="remove-from-playlist"]').addEventListener('click', async () => {
            if (contextTrackId && currentPlaylistId) {
                await api('remove_from_playlist', {
                    playlist_id: currentPlaylistId,
                    track_id: contextTrackId
                });
                // Reload playlist tracks
                const data = await api('playlist_tracks', {playlist_id: currentPlaylistId});
                player.tracks = data.tracks;
                renderTracks(data.tracks, true);
                await loadPlaylists(); // Update track count
            }
            document.getElementById('context-menu').classList.remove('show');
        });

        // Playlist context menu
        let contextPlaylistId = null;

        document.getElementById('playlist-list').addEventListener('contextmenu', (e) => {
            e.preventDefault();
            const item = e.target.closest('.playlist-item');
            if (item) {
                contextPlaylistId = item.dataset.id;
                const menu = document.getElementById('playlist-context-menu');
                menu.style.left = e.pageX + 'px';
                menu.style.top = e.pageY + 'px';
                menu.classList.add('show');
            }
        });

        document.querySelector('[data-action="rename-playlist"]').addEventListener('click', async () => {
            if (contextPlaylistId) {
                const name = prompt('New playlist name:');
                if (name) {
                    await api('rename_playlist', {playlist_id: contextPlaylistId, name});
                    await loadPlaylists();
                }
            }
            document.getElementById('playlist-context-menu').classList.remove('show');
        });

        document.querySelector('[data-action="delete-playlist"]').addEventListener('click', async () => {
            if (contextPlaylistId && confirm('Delete this playlist?')) {
                await api('delete_playlist', {playlist_id: contextPlaylistId});
                await loadPlaylists();
            }
            document.getElementById('playlist-context-menu').classList.remove('show');
        });

        // Close playlist context menu on click outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#playlist-context-menu')) {
                document.getElementById('playlist-context-menu').classList.remove('show');
            }
        });

        // Update Now Playing when track changes
        const originalLoadTrack = player.loadTrack.bind(player);
        player.loadTrack = function(track, index, startPlaying = false) {
            originalLoadTrack(track, index, startPlaying);
            if (document.getElementById('now-playing-modal').classList.contains('show')) {
                updateNowPlayingView();
            }
        };

        // Initialize
        loadView('all');
        loadPlaylists();
        player.setVolume(80);
    </script>
</body>
</html>
