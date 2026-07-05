export default function ApplicationLogo(props) {
    return (
        <svg
            {...props}
            viewBox="0 14 128 140"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            aria-label="FarmTask"
        >
            <defs>
                <clipPath id="farmtask-paddock">
                    <polygon points="0,40 128,16 112,152 0,152" />
                </clipPath>
            </defs>

            <polygon points="0,40 128,16 112,152 0,152" fill="#1A5C38" />

            <g clipPath="url(#farmtask-paddock)">
                <path
                    d="M-16,124 C0,68 36,34 68,28 C102,22 136,44 144,80 C150,108 137,138 112,152 C78,168 16,162 -8,138 C-26,122 -30,140 -16,124Z"
                    fill="none"
                    stroke="white"
                    strokeWidth="2.2"
                    opacity="0.2"
                />
                <path
                    d="M8,114 C22,76 50,54 76,50 C103,46 128,64 132,94 C136,116 123,140 98,150 C68,162 20,156 6,132 C-6,116 -6,126 8,114Z"
                    fill="none"
                    stroke="white"
                    strokeWidth="2.2"
                    opacity="0.5"
                />
                <path
                    d="M34,102 C46,74 66,60 84,58 C103,56 117,70 113,92 C110,112 94,122 76,122 C54,124 28,108 34,102Z"
                    fill="none"
                    stroke="white"
                    strokeWidth="2.2"
                    opacity="0.8"
                />
                <circle cx="78" cy="80" r="10" fill="white" opacity="0.9" />
            </g>
        </svg>
    );
}
