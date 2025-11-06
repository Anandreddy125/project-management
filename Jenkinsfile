pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }

    environment {
        // Repository & credentials
        GIT_REPO              = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID    = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"

        // Dynamic variables
        IMAGE_NAME            = ""
        DEPLOY_ENV            = ""
        TAG_TYPE              = ""
    }

    parameters {
        choice(name: 'BRANCH_PARAM', choices: ['main', 'master'], description: 'Select branch to build manually')
        booleanParam(name: 'ROLLBACK', defaultValue: false, description: 'Rollback to TARGET_VERSION instead of deploy')
        string(name: 'TARGET_VERSION', defaultValue: '', description: 'Target Docker tag for rollback (if enabled)')
    }

    triggers {
        githubPush()
    }

    stages {

        stage('Check Trigger Type') {
            steps {
                script {
                    // Protect pipeline from tag-triggered builds unless it’s a master release
                    if (env.GIT_BRANCH?.startsWith("refs/tags/") || env.BRANCH_NAME?.startsWith("refs/tags/")) {
                        def tagRef = env.GIT_BRANCH ?: env.BRANCH_NAME
                        echo "🚫 Tag push detected: ${tagRef}"

                        // If it's a tag push but not for master, skip the build
                        if (!tagRef.contains("master")) {
                            echo "⏭️ Skipping non-master tag build."
                            currentBuild.result = 'SUCCESS'
                            return
                        }
                    }

                    echo "✅ Normal branch push detected — continuing pipeline..."
                }
            }
        }

        stage('Clean Workspace') {
            steps {
                cleanWs()
            }
        }

        stage('Checkout Code') {
            steps {
                script {
                    def branchName = env.BRANCH_NAME ?: params.BRANCH_PARAM
                    echo "🔹 Checking out branch: ${branchName}"

                    checkout([$class: 'GitSCM',
                        branches: [[name: "*/${branchName}"]],
                        userRemoteConfigs: [[
                            url: "${GIT_REPO}",
                            credentialsId: "${GIT_CREDENTIALS_ID}"
                        ]],
                        extensions: [
                            [$class: 'CloneOption', depth: 0, noTags: false, shallow: false],
                            [$class: 'CheckoutOption', timeout: 30]
                        ]
                    ])
                    env.ACTUAL_BRANCH = branchName
                }
            }
        }

        stage('Determine Environment') {
            steps {
                script {
                    if (env.ACTUAL_BRANCH === "main" || env.ACTUAL_BRANCH === "staging") {
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/farhan-testing"
                        env.TAG_TYPE   = "commit"
                    } else if (env.ACTUAL_BRANCH === "master") {
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/sample-private"
                        env.TAG_TYPE   = "release"
                    } else {
                        error("❌ Unsupported branch: ${env.ACTUAL_BRANCH}")
                    }

                    echo """
                    🌿 Environment Details
                    ---------------------
                    Branch:        ${env.ACTUAL_BRANCH}
                    Environment:   ${env.DEPLOY_ENV}
                    Docker Image:  ${env.IMAGE_NAME}
                    Tag Type:      ${env.TAG_TYPE}
                    """
                }
            }
        }

        stage('Set Image Tag') {
            steps {
                script {
                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback requested but TARGET_VERSION is empty.")
                        }
                        env.IMAGE_TAG = params.TARGET_VERSION.trim()
                        echo "⤴️ Rollback mode — using tag ${env.IMAGE_TAG}"

                    } else if (env.TAG_TYPE === "commit") {
                        def commitId = sh(script: "git rev-parse --short HEAD", returnStdout: true).trim()
                        env.IMAGE_TAG = "${env.DEPLOY_ENV}-${commitId}"
                        echo "🏷️ Using commit-based tag: ${env.IMAGE_TAG}"

                    } else {
                        // For production: prefer Git tag if exists
                        def tagName = sh(script: "git describe --tags --exact-match HEAD 2>/dev/null || true", returnStdout: true).trim()
                        if (!tagName) {
                            echo "⚠️ No release tag found. Using commit ID instead."
                            def commitId = sh(script: "git rev-parse --short HEAD", returnStdout: true).trim()
                            env.IMAGE_TAG = "build-${commitId}"
                        } else {
                            env.IMAGE_TAG = tagName
                        }
                        echo "🏷️ Final image tag: ${env.IMAGE_TAG}"
                    }
                }
            }
        }
    }
}
