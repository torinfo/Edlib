const borderColor = '#d1d5da';
export default {
    colors: {
        primary: '#1d446b',
        secondary: '#82df66',
        tertiary: '#2195f3',
        success: '#28a745',
        danger: '#dc3545',
        gray: '#545454',
        link: '#4DCFF3',
        defaultFont: '#545454',
        background: '#ffffff',
        border: borderColor,
        green: '#a7da19',
        alert: {
            primary: {
                background: '#43aeff',
                font: '#1d3c63',
            },
            warning: {
                background: '#fff3cd',
                font: '#856404',
            },
            danger: {
                background: '#f8d7da',
                font: '#721c24',
            },
        },
    },
    border: `1px solid ${borderColor}`,
    padding: '5px',
    fontSize: 16,
    breakpoints: {
        xs: 0,
        sm: 600,
        md: 960,
        lg: 1280,
        xl: 1920,
    },
    doku: {
        defaultSpacing: 24,
    },
};
